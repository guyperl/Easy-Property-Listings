<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Processes a custom edit
 *
 * @since  2.4
 * @param  array $args The $_POST array being passeed
 * @return array $output Response messages
 */
function epl_edit_contact( $args ) {
	$contact_edit_role = apply_filters( 'epl_edit_contacts_role', 'manage_options' );

	if ( ! is_admin() || ! current_user_can( $contact_edit_role ) ) {
		wp_die( __( 'You do not have permission to edit this contact.', 'epl' ) );
	}

	if ( empty( $args ) ) {
		return;
	}

	$contact_info = $args['contactinfo'];
	$contact_id   = (int)$args['contactinfo']['id'];
	$nonce         = $args['_wpnonce'];

	if ( ! wp_verify_nonce( $nonce, 'edit-contact' ) ) {
		wp_die( __( 'Cheatin\' eh?!', 'epl' ) );
	}

	$contact = new EPL_Contact( $contact_id );
	if ( empty( $contact->ID ) ) {
		return false;
	}

	$defaults = array(
		'name'    => '',
		'email'   => '',
		'user_id' => 0
	);

	$contact_info = wp_parse_args( $contact_info, $defaults );

	if ( ! is_email( $contact_info['email'] ) ) {
		epl_set_error( 'epl-invalid-email', __( 'Please enter a valid email address.', 'epl' ) );
	}

	if ( epl_get_errors() ) {
		return;
	}

	// Sanitize the inputs
	$contact_data            = array();
	$contact_data['name']    = strip_tags( stripslashes( $contact_info['name'] ) );
	$contact_data['email']   = $contact_info['email'];

	$contact_data = apply_filters( 'epl_edit_contact_info', $contact_data, $contact_id );

	$contact_data = array_map( 'sanitize_text_field', $contact_data );

	do_action( 'epl_pre_edit_contact', $contact_id, $contact_data );

	$output         = array();

	if ( $contact->update( $contact_data ) ) {

		$output['success']       = true;
		$output['contact_info'] = $contact_data;

	} else {

		$output['success'] = false;

	}

	do_action( 'epl_post_edit_contact', $contact_id, $contact_data );

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		header( 'Content-Type: application/json' );
		echo json_encode( $output );
		wp_die();
	}

	return $output;

}
add_action( 'epl_edit-contact', 'epl_edit_contact', 10, 1 );

/**
 * Delete a contact
 *
 * @since  2.4
 * @param  array $args The $_POST array being passeed
 * @return int         Wether it was a successful deletion
 */
function epl_contact_delete( $args ) {

	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission to delete this contact.', 'epl' ) );
	}

	if ( empty( $args ) ) {
		return;
	}

	$contact_id   = (int)$args['contact_id'];
	$confirm       = ! empty( $args['epl-contact-delete-confirm'] ) ? true : false;
	$nonce         = $args['_wpnonce'];

	if ( ! wp_verify_nonce( $nonce, 'delete-contact' ) ) {
		wp_die( __( 'Cheatin\' eh?!', 'epl' ) );
	}

	if ( ! $confirm ) {
		epl_set_error( 'contact-delete-no-confirm', __( 'Please confirm you want to delete this contact', 'epl' ) );
	}

	if ( epl_get_errors() ) {
		wp_redirect( admin_url( 'edit.php?page=epl-contacts&view=overview&id=' . $contact_id ) );
		exit;
	}

	$contact = new EPL_Contact( $contact_id );

	do_action( 'epl_pre_delete_contact', $contact_id, $confirm );

	$success = false;

	if ( $contact->ID > 0 ) {

		$listings_array = $contact->listing_ids;
		
		// delete contact from meta of interested listings
		foreach($listings_array as $listing_id) {
			$contact->remove_listing($listing_id);
		}
			

		$success        = $contact->delete( $contact->id );

		if ( $success ) {
			
			$redirect = admin_url( 'admin.php?page=epl-contacts&epl-message=contact-deleted' );

		} else {

			epl_set_error( 'epl-contact-delete-failed', __( 'Error deleting contact', 'epl' ) );
			$redirect = admin_url( 'admin.php?page=epl-contacts&view=delete&id=' . $contact_id );

		}

	} else {

		epl_set_error( 'epl-contact-delete-invalid-id', __( 'Invalid Contact ID', 'epl' ) );
		$redirect = admin_url( 'admin.php?page=epl-contacts' );

	}

	wp_redirect( $redirect );
	exit;

}
add_action( 'epl_delete-contact', 'epl_contact_delete', 10, 1 );


/**
 * Save a customer note being added
 *
 * @since  2.4
 * @param  array $args The $_POST array being passeed
 * @return object         the comment object
 */
function epl_contact_save_note( $args ) {

	$contact_view_role = apply_filters( 'epl_view_contacts_role', 'manage_options' );

	if ( ! is_admin() || ! current_user_can( $contact_view_role ) ) {
		wp_die( __( 'You do not have permission to edit this customer.', 'epl' ) );
	}

	if ( empty( $args ) ) {
		return;
	}

	$contact_note 	= trim( sanitize_text_field( $args['contact_note'] ) );
	$listing_id 	= trim( sanitize_text_field( $args['listing_id'] ) );
	$note_type 	    = trim( sanitize_text_field( $args['note_type'] ) );

	$contact_id   = (int)$args['contact_id'];
	$nonce         = $args['add_contact_note_nonce'];

	if ( ! wp_verify_nonce( $nonce, 'add_contact_note_nonce' ) ) {
		wp_die( __( 'Cheatin\' eh?!', 'epl' ) );
	}

	if ( empty( $contact_note ) ) {
		epl_set_error( 'empty-customer-note', __( 'A note is required', 'epl' ) );
	}

	if ( epl_get_errors() ) {
		epl_set_error();
		return;
	}

	do_action( 'epl_pre_insert_contact_note', $contact_id, $new_note, $listing_id, $note_type );
	
	$contact = new EPL_Contact( $contact_id );
	$note_object = $contact->add_note( $contact_note,$note_type,$listing_id );

	

	if ( ! empty( $note_object ) && ! empty( $contact->id ) ) {

		ob_start();
		?>
		<tr data-activity-id="<?php echo $note_object->comment_ID ;?>" id="activity-id-<?php echo $note_object->comment_ID ;?>" class="epl-contact-activity-row epl-contact-activity-<?php echo $note_object->comment_type; ?>" >
			<td><?php echo stripslashes( $contact->get_activity_type($note_object->comment_type ) ); ?></td>
			<td>
				<?php
					if($note_object->comment_post_ID > 0) {
						echo '<div class="epl-contact-inline-lis-details">';
						echo '<span class="epl-contact-inline-lis-img">';
						echo get_the_post_thumbnail($note_object->comment_post_ID, array(50,50));
						echo '</span>';
						echo '<span class="epl-contact-inline-lis-title">';
						echo '<a href="'.get_permalink($note_object->comment_post_ID).'">'.get_the_title($note_object->comment_post_ID).'</a>';
						echo '</span>';
						echo '</div>';
					}
					echo stripslashes( $note_object->comment_content );
				?>
			</td>
			<td>
				<?php
					echo date_i18n( get_option( 'date_format' ), strtotime( $note_object->comment_date ) );
				?>
			</td>
		</tr>
		<?php
		$output = ob_get_contents();
		ob_end_clean();

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			echo $output;
			exit;
		}

		return $note_object;

	}

	return false;

}
add_action( 'epl_add-contact-note', 'epl_contact_save_note', 10, 1 );

/**
 * Save a contact listing being added
 *
 * @since  2.4
 * @param  array $args The $_POST array being passeed
 * @return object
 */
function epl_contact_save_listing( $args ) {
	$contact_add_listing_role = apply_filters( 'epl_add_contacts_listing', 'manage_options' );

	if ( ! is_admin() || ! current_user_can( $contact_add_listing_role ) ) {
		wp_die( __( 'You do not have permission to add listing.', 'epl' ) );
	}

	if ( empty( $args ) ) {
		return;
	}

	$post_fields = array('post_title');

	$ignore_fields = array('add_contact_listing_nonce','epl_actiion','contact_id');

	$nonce         = $args['add_contact_listing_nonce'];

	if ( ! wp_verify_nonce( $nonce, 'add_contact_listing_nonce' ) ) {
		wp_die( __( 'Cheatin\' eh?!', 'epl' ) );
	}
	if ( epl_get_errors() ) {
		epl_print_error();
		return;
	}

	do_action( 'epl_pre_insert_contact_listing', $args );
	if($args['property_owner'] > 0) {
		$insert_post_array = array('post_status'    =>  'publish', 'post_type'  =>  'contact_listing');
		$insert_meta_array = array();
		foreach($args as $arg_key   =>  $arg_value) {
			if( in_array($arg_key,$post_fields) ) {
				$insert_post_array[$arg_key] = $arg_value;
			} elseif(!in_array($arg_key,$ignore_fields)) {
				$insert_meta_array[$arg_key] = $arg_value;
			}
		}
		if($insert_id = wp_insert_post($insert_post_array)) {
			foreach($insert_meta_array as $meta_key =>  $meta_value) {
				update_post_meta($insert_id,$meta_key,$meta_value);
			}
		} else {
			return false;
		}

	}
	$inserted_lisitng = get_post($insert_id);
	if ( ! empty( $inserted_lisitng )  ) {

		ob_start();
		?>
		<tr data-activity-id="<?php echo $inserted_lisitng->ID ;?>" id="activity-id-<?php echo $inserted_lisitng->ID ;?>" class="epl-contact-activity-row " >
			<td><?php echo get_post_meta($inserted_lisitng->ID,'property_listing_type',true); ?></td>
			<td>
				<?php
					echo '<a href="'.get_edit_post_link($inserted_lisitng->ID).'">'.$inserted_lisitng->post_title.'</a>';
				?>
			</td>
			<td>
				<?php echo get_post_meta($inserted_lisitng->ID,'property_listing_status',true); ?>
			</td>
		</tr>
		<?php
		$output = ob_get_contents();
		ob_end_clean();

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			echo $output;
			exit;
		}

		return $inserted_lisitng;

	}

	return false;

}
add_action( 'epl_add-contact-listing', 'epl_contact_save_listing', 10, 1 );



/**
 * Processes a custom edit
 *
 * @since  2.4
 * @param  array $args The $_POST array being passeed
 * @return array $output Response messages
 */
function epl_meta_contact( $args ) {

	$contact_edit_role = apply_filters( 'epl_edit_contacts_role', 'manage_options' );

	if ( ! is_admin() || ! current_user_can( $contact_edit_role ) ) {
		wp_die( __( 'You do not have permission to edit this contact.', 'epl' ) );
	}

	if ( empty( $args ) ) {
		return;
	}
	
	$nonce         = $args['_wpnonce'];
	if ( ! wp_verify_nonce( $nonce, 'meta-contact' ) ) {
		wp_die( __( 'Cheatin\' eh?!', 'epl' ) );
	}

	$contact_id   = (int)$args['contact_id'];
	$contact = new EPL_Contact( $contact_id );
	if ( empty( $contact->ID ) ) {
		return false;
	}
	
	$not_meta_fields = array('epl_form_builder_form_submit','contact_id','_wpnonce','epl_action');

	$post_fields = array('post_title','post_content','ID','post_author');

	$field_updates = array('ID' =>  $contact_id);
	foreach($args as $key	=>	$value) {
		if( !in_array($key,$not_meta_fields) ) {

			// check if post fields
			if( in_array($key,$post_fields) ) {
				$field_updates[$key] = $value;
			} else {
				$contact->update_meta($key,$value);
			}


		}

	}
	wp_update_post($field_updates);

	$redirect = admin_url( 'admin.php?page=epl-contacts&view=meta&id=' . $contact_id );
	wp_redirect( $redirect );
	exit;

}
add_action( 'epl_meta-contact', 'epl_meta_contact', 10, 1 );


/**
 * create a new contact from backend
 *
 * @since  2.4
 * @param  array $args The $_POST array being passeed
 * @return array $output Response messages
 */
function epl_new_contact( $args ) {

	$contact_create_role = apply_filters( 'epl_create_contacts_role', 'manage_options' );

	if ( ! is_admin() || ! current_user_can( $contact_create_role ) ) {
		wp_die( __( 'You do not have permission to edit this contact.', 'epl' ) );
	}

	if ( empty( $args ) ) {
		return;
	}
	
	$nonce         = $args['_wpnonce'];
	if ( ! wp_verify_nonce( $nonce, 'new-contact' ) ) {
		wp_die( __( 'Cheatin\' uhh?!', 'epl' ) );
	}

	$contact_id   = (int)$args['contact_id'];
	$contact = new EPL_Contact( $contact_id );
	if ( empty( $contact->ID ) ) {
		return false;
	}
	
	$contact->update($args);
	
	$redirect = admin_url( 'admin.php?page=epl-contacts&view=meta&id=' . $contact_id );
	wp_redirect( $redirect );
	exit;

}
add_action( 'epl_new-contact', 'epl_new_contact', 10, 1 );

/**
 * Update contact category
 * @since 2.4
 * @return bool true if updated
 */
function contact_category_update() {
	if( (int) $_POST['contact_id'] > 0 && trim($_POST['type']) != '' ) {

		$contact = new EPL_Contact($_POST['contact_id']);
		echo $contact->update_meta( 'contact_category',trim($_POST['type']) );
		wp_die();
	}
}
add_action('wp_ajax_contact_category_update','contact_category_update');

/**
 * Add/Update contact tags
 * @since 2.4
 * @return bool true if updated
 */
	function contact_tag_add() {
		if( (int) $_POST['contact_id'] > 0 && (int) $_POST['term_id'] > 0 ) {

			wp_set_object_terms( absint($_POST['contact_id']), absint($_POST['term_id']), 'contact_tag', true );
			wp_die(1);
		}
	}
	add_action('wp_ajax_contact_tags_update','contact_tag_add');

/**
 * delete contact tags
 * @since 2.4
 * @return bool true if updated
 */
function contact_tag_remove() {
	if( (int) $_POST['contact_id'] > 0 && (int) $_POST['term_id'] > 0 ) {

		wp_remove_object_terms( absint($_POST['contact_id']), absint($_POST['term_id']), 'contact_tag' );
		wp_die(1);
	}
}
add_action('wp_ajax_contact_tag_remove','contact_tag_remove');

/**
 * @param $contact
 * @since 2.4
 * renders contact action menus
 */
function epl_contact_action_menus($contact) { ?>
	<div class="contact-action-menu">
	<ul class="epl_contact_quick_actions">
		<li>
			<a  class="contact-action-category" href="#" title="<?php _e('Contact Category'); ?>">
				<span class="dashicons dashicons-flag"></span>
				<b class="caret"></b>
			</a>
			<ul class="contact_category_suggestions">
				<?php

					$cats = apply_filters('epl_contact_categories',array(
						'appraisal'     =>  __('Appraisal','epl'),
						'lead'          =>  __('Lead','epl'),
						'past_customer' =>  __('Past Customer','epl'),
						'contract'      =>  __('Contract','epl'),
						'buyer'         =>  __('Buyer','epl'),
						'seller'        =>  __('Seller','epl'),
					));

					foreach($cats as $cat_key   =>  $cat_label) :
						echo '<li> <a href="#" data-key="'.$cat_key.'" data-label="'.$cat_label.'">'.$cat_label.'</a></li>';
					endforeach;
				?>
			</ul>
		</li>
		<li>
			<a  class="contact-action-tag" href="#" title="<?php _e('Contact Tags'); ?>">
				<span class="dashicons dashicons-tag"></span>
				<b class="caret"></b>
			</a>
			<div class="contact-tags-find">
				<input type="text" id="contact-tag-hint" value=""/>
				<ul class="contact_tags_suggestions">
					<?php
						$contact_tags = get_terms('contact_tag',array( 'hide_empty' =>  false));
						if( !empty($contact_tags) ) {

							foreach($contact_tags as $contact_tag) {
								$bgcolor = epl_get_contact_tag_bgcolor($contact_tag->term_id);

								echo '<li data-bg="'.$bgcolor.'" style="background:'.$bgcolor.';color:#fff" data-id="'.$contact_tag->term_id.'" >'.$contact_tag->name.'</li>';
							}
						}
					?>

				</ul>
			</div>

		</li>
		<?php do_action('post_contact_custom_quick_edit_options', $contact); ?>
	</ul>
</div> <?php
}
add_action('epl_contact_action_menus','epl_contact_action_menus');

/**
 * @param $contact
 * @since 2.4
 * renders contact header
 */
function epl_contact_entry_header($contact) { ?>
	<div class="contact-entry-header">
		<h1 class="epl-contact-title">
			<?php
				echo $contact->name;
			?>
			<span>
				<?php
					echo $contact->get_meta('contact_category');
				?>
			</span>
		</h1>
	</div> <?php
}
add_action('epl_contact_entry_header','epl_contact_entry_header');

/**
 * renders assigned tag for contact
 * @param $contact
 * @since 2.4
 * renders contact header
 */
function epl_contact_assigned_tags($contact) { ?>
	<div class="contact-assigned-tags-wrap">
		<ul class="contact-assigned-tags">
			<?php
				$contact_tags = wp_get_object_terms( $contact->id,  'contact_tag' );
				if ( ! empty( $contact_tags ) ) {
					if ( ! is_wp_error( $contact_tags ) ) {
						foreach( $contact_tags as $term ) {
							$bgcolor = epl_get_contact_tag_bgcolor( $term->term_id);
							echo '<li data-id="'.$term->term_id.'" id="contact-tag-'.$term->term_id.'" style="background:'.$bgcolor.'">' . esc_html( $term->name ) . '<span class="dashicons dashicons-no contact-tag-del"></span></li>';
						}
					}
				}
			?>
		</ul>
	</div> <?php
}
add_action('epl_contact_assigned_tags','epl_contact_assigned_tags');

function epl_contact_background_info($contact) {
	echo '<div class="epl-contact-bg-info-wrap">';
		echo '<h4>'.__('Background Info','epl').'</h4>';
		echo '<div class="epl-contact-bg-info">';
			echo $contact->background_info;
		echo '</div>';

	echo '</div>';
}
add_action('epl_contact_background_info','epl_contact_background_info');

function epl_contact_avatar($contact) { ?>
	<div class="avatar-wrap left" id="contact-avatar">
			<?php echo get_avatar( $contact->email , apply_filters('epl_contact_gravatar_size',160) ); ?><br />
		</div> <?php
	}
	add_action('epl_contact_avatar','epl_contact_avatar');

function epl_contact_social_icons($contact) { ?>

	<?php if( $contact->get_meta('contact_facebook') != '' ) :?>
		<a href="<?php echo $contact->get_meta('contact_facebook'); ?>">
			<span class="epl-contact-social-icon">f</span>
		</a>
	<?php endif; ?>
	<?php if( $contact->get_meta('contact_twitter') != '' ) :?>
		<a href="<?php echo $contact->get_meta('contact_twitter'); ?>">
			<span class="epl-contact-social-icon">t</span>
		</a>
	<?php endif; ?>
	<?php if( $contact->get_meta('contact_google_plus') != '' ) :?>
		<a href="<?php echo $contact->get_meta('contact_google_plus'); ?>">
			<span class="epl-contact-social-icon">g+</span>
		</a>
	<?php endif; ?>
	<?php if( $contact->get_meta('contact_linked_in') != '' ) :?>
		<a href="<?php echo $contact->get_meta('contact_linked_in'); ?>">
			<span class="epl-contact-social-icon">in</span>
		</a>
	<?php endif; ?>
	<?php do_action('epl_contact_more_social_icons',$contact);
}
add_action('epl_contact_social_icons','epl_contact_social_icons');

function epl_contact_contact_details($contact) { ?>

	<span class="contact-name info-item editable"><span data-key="name"><?php echo $contact->get_meta('contact_first_name').' '.$contact->get_meta('contact_last_name'); ?></span></span>
	<span class="contact-email info-item editable" data-key="email">
							<span class="dashicons dashicons-email epl-contact-icons"></span>
		<?php echo $contact->email; ?>
						</span>
	<?php if( $contact->get_meta('contact_phone') != '' ) :?>
		<span class="contact_phone info-item editable" data-key="phone">
							<span class="dashicons dashicons-phone epl-contact-icons"></span>
			<?php echo $contact->get_meta('contact_phone'); ?>
						</span>
	<?php endif; ?>
	<?php if( $contact->get_meta('contact_mobile') != '' ) :?>
		<span class="contact_mobile info-item editable" data-key="smartphone">
							<span class="dashicons dashicons-smartphone epl-contact-icons"></span>
			<?php echo $contact->get_meta('contact_mobile'); ?>
						</span>
	<?php endif; ?>
	<?php if( $contact->get_meta('contact_website') != '' ) :?>
		<span class="contact_website info-item editable" data-key="website">
							<span class="dashicons dashicons-admin-links epl-contact-icons"></span>
			<?php echo $contact->get_meta('contact_website'); ?>
						</span>
	<?php endif;
}
add_action('epl_contact_contact_details','epl_contact_contact_details');

/**
function epl_contact_recent_interests($contact) { ?>
	<h3><?php _e( 'Listings', 'epl' ); ?></h3>
	<?php
		$listing_ids = $contact->listing_ids;
		if( !empty($listing_ids) ) {
			$listings    = get_posts( array( 'post__in' => $listing_ids, 'post_type'	=>	epl_all_post_types() ) );
			$listings    = array_slice( $listings, 0, 10 );
		}
	?>
	<table class="wp-list-table widefat striped listings">
		<thead>
		<tr>
			<th><?php _e( 'ID', 'epl' ); ?></th>
			<th><?php _e( 'Title', 'epl' ); ?></th>
			<th><?php _e( 'Published Date', 'epl' ); ?></th>
			<th><?php _e( 'Status', 'epl' ); ?></th>
			<th><?php _e( 'Actions', 'epl' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php if ( ! empty( $listings ) ) : ?>
			<?php foreach ( $listings as $listing ) : ?>
				<tr>
					<td><?php echo $listing->ID; ?></td>
					<td><?php echo  $listing->post_title; ?></td>
					<td><?php echo date_i18n( get_option( 'date_format' ), strtotime( $listing->post_date ) ); ?></td>
					<td><?php echo get_post_meta( $listing->ID,'property_status',true) ?></td>
					<td>
						<a title="<?php _e( 'View Details for Listing', 'epl' ); echo ' ' . $listing->ID; ?>" href="<?php echo admin_url( 'post.php?&action=edit&post=' . $listing->ID ); ?>">
							<?php _e( 'View Details', 'epl' ); ?>
						</a>
						<?php do_action( 'epl_contact_recent_listings_actions', $contact, $listing ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else: ?>
			<tr><td colspan="5"><?php _e( 'No Listings Found', 'epl' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table> <?php
}
add_action('epl_contact_recent_interests','epl_contact_recent_interests');
**/

function epl_contact_recent_interests($contact,$number = 10, $paged = 1 ,$orderby = 'post_date', $order = 'DESC') { ?>
	<?php do_action('epl_contact_add_listing_form', $contact); ?>
	<h3 class="epl-contact-activity-title"><?php _e( 'Listings', 'epl' ); ?> <span class="epl-contact-add-listing"><?php _e('Add Listing'); ?></span></h3>
	<input type="hidden" id="epl-listing-table-orderby" value="<?php echo $orderby; ?>"/>
	<input type="hidden" id="epl-listing-table-order" value="<?php echo $order; ?>">
	<?php
	epl_contact_get_listings_html($contact,$number, $paged,$orderby, $order);
}
add_action('epl_contact_recent_interests','epl_contact_recent_interests');

function epl_contact_recent_activities($contact,$number = 10, $paged = 1 ,$orderby = 'comment_date', $order = 'DESC') { ?>
	<?php do_action('epl_contact_add_activity_form', $contact); ?>
	<h3 class="epl-contact-activity-title"><?php _e( 'Activities', 'epl' ); ?> <span class="epl-contact-add-activity"><?php _e('Add Activity'); ?></span></h3>
	<input type="hidden" id="epl-contact-table-orderby" value="<?php echo $orderby; ?>"/>
	<input type="hidden" id="epl-contact-table-order" value="<?php echo $order; ?>">
	<?php
	epl_contact_get_activities_html($contact,$number, $paged,$orderby, $order);
}
add_action('epl_contact_recent_activities','epl_contact_recent_activities');

function epl_contact_get_activities_html($contact,$number = 10, $paged = 1 ,$orderby = 'comment_date', $order = 'DESC') {

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		extract($_POST);
	}
	if( !is_object($contact) )
		$contact = new EPL_Contact( $contact );

	//epl_print_r($contact,true);
	$activities = $contact->get_notes($number,$paged,$orderby,$order);
	?>
	<div id="epl-contact-activity-table-wrapper">
		<table class="wp-list-table widefat striped epl-contact-activities">
			<thead>
			<tr class="epl-contact-activities-table-heads">
				<th class="epl-sorted-<?php echo strtolower($order); ?>" data-sort="comment_type"><?php _e( 'Type', 'epl' ); ?></th>
				<th class="epl-sorted-<?php echo strtolower($order); ?>" data-sort="comment_content"><?php _e( 'Comment', 'epl' ); ?></th>
				<th class="epl-sorted-<?php echo strtolower($order); ?>" data-sort="comment_date"><?php _e( 'Date', 'epl' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php if ( ! empty( $activities ) ) : ?>
				<?php foreach ( $activities as $activity ) : ?>
					<tr data-activity-id="<?php echo $activity->comment_ID ;?>" id="activity-id-<?php echo $activity->comment_ID ;?>" class="epl-contact-activity-row epl-contact-activity-<?php echo $activity->comment_type; ?>" >
						<td><?php echo $contact->get_activity_type($activity->comment_type) ?></td>
						<td>
							<?php
								if($activity->comment_post_ID > 0) {
									echo '<div class="epl-contact-inline-lis-details">';
									echo '<span class="epl-contact-inline-lis-img">';
										echo get_the_post_thumbnail($activity->comment_post_ID, array(50,50));
									echo '</span>';
									echo '<span class="epl-contact-inline-lis-title">';
									echo '<a href="'.get_permalink($activity->comment_post_ID).'">'.get_the_title($activity->comment_post_ID).'</a>';
									echo '</span>';
									echo '</div>';
								}

								echo  $activity->comment_content;
							?>
						</td>
						<td><?php echo date_i18n( get_option( 'date_format' ), strtotime( $activity->comment_date ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else: ?>
				<tr><td colspan="5"><?php _e( 'No Listings Found', 'epl' ); ?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		<span  data-page="<?php echo $paged + 1; ?>" class="epl-contact-load-activities"><?php _e('Load More Activities'); ?> </span>
	</div><?php
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		wp_die();
	}

}
add_action('wp_ajax_epl_contact_get_activity_table','epl_contact_get_activities_html',10,5);

function epl_contact_get_listings_html($contact,$number = 10, $paged = 1 ,$orderby = 'post_date', $order = 'DESC') {

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		extract($_POST);
	}
	if( !is_object($contact) )
		$contact = new EPL_Contact( $contact );

	//epl_print_r($contact,true);
	$activities = get_posts(
		array(
			'post_type'     =>  'contact_listing',
			'post_status'   =>  'publish',
			'meta_key'      =>  'property_owner',
			'meta_value'    =>  $contact->id
		)
	);
	?>
	<div id="epl-contact-listing-table-wrapper">
	<table class="wp-list-table widefat striped epl-contact-listings">
		<thead>
		<tr class="epl-contact-listings-table-heads">
			<th class="epl-sorted-<?php echo strtolower($order); ?>" data-sort="listing_type"><?php _e( 'Type', 'epl' ); ?></th>
			<th class="epl-sorted-<?php echo strtolower($order); ?>" data-sort="post_content"><?php _e( 'Title', 'epl' ); ?></th>
			<th class="epl-sorted-<?php echo strtolower($order); ?>" data-sort="listing_status"><?php _e( 'Status', 'epl' ); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php if ( ! empty( $activities ) ) : ?>
			<?php foreach ( $activities as $inserted_lisitng ) : ?>
				<tr data-activity-id="<?php echo $inserted_lisitng->ID ;?>" id="activity-id-<?php echo $inserted_lisitng->ID ;?>" class="epl-contact-activity-row " >
					<td><?php echo get_post_meta($inserted_lisitng->ID,'property_listing_type',true); ?></td>
					<td>
						<?php
							echo '<a href="'.get_edit_post_link($inserted_lisitng->ID).'">'.$inserted_lisitng->post_title.'</a>';
						?>
					</td>
					<td>
						<?php echo get_post_meta($inserted_lisitng->ID,'property_listing_status',true); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php else: ?>
			<tr><td colspan="5"><?php _e( 'No Listings Found', 'epl' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>
	</div><?php
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		wp_die();
	}

}
add_action('wp_ajax_epl_contact_get_listing_table','epl_contact_get_listings_html',10,5);

function epl_contact_add_activity_form($contact) {
	$form_builder = new EPL_FORM_BUILDER();
	$listing_ids = $contact->listing_ids;
	$listings_opts = array(''   =>  __('No Listing') );
	if( !empty($listing_ids) ) {
		$listings    = get_posts( array( 'post__in' => $listing_ids, 'post_type'	=>	epl_all_post_types() ) );
			if( !empty($listings) ) :
			foreach($listings as $listing) :
				$listings_opts[$listing->ID] = $listing->post_title;
			endforeach;
		endif;
	}
	$fields = array(
		array(
			'label'		=>	__('Add Activity' , 'epl'),
			'class'		=>	'col-1 epl-inner-div',
			'id'		=>	'epl-contact-add-activity-wrap',
			'help'		=>	__('' , 'epl') . '<hr/>',
			'fields'	=>	array(
				array(
					'name'		=>	'contact_activity_content',
					'class'		=>	'contact-note-input',
					'type'		=>	'textarea',
				),
				array(
					'name'		=>	'contact_activity_type',
					'class'		=>	'contact-note-select',
					'type'		=>	'select',
					'opts'	    =>	$contact->get_activity_types()
				),
				array(
					'name'		=>	'contact_activity_listing',
					'class'		=>	'contact-note-select',
					'type'		=>	'select',
					'opts'	    =>	$listings_opts
				),
				array(
					'name'		=>	'contact_activity_submit',
					'value'		=>	__('Add','epl'),
					'class'     =>  'button button-primary',
					'type'		=>	'submit',
				),
			)
		),

	);
	$form_builder->set_form_attributes( array('name'    =>  'epl_contact_add_activity_form', 'id'    =>  'epl_contact_add_activity_form') );
	$form_builder->add_nonce('add_contact_note_nonce');
	$form_builder->add_sections($fields);
	echo '<div class="epl-contact-add-activity-form-wrap">';
		$form_builder->render_form();
	echo '</div>';

}
add_action('epl_contact_add_activity_form','epl_contact_add_activity_form');

function epl_contact_add_listing_form($contact) {
	global $epl_settings;
	$form_builder = new EPL_FORM_BUILDER();
	$listing_types = epl_get_active_post_types();
	$fields = array(
		array(
			'label'		=>	__('Add Listing' , 'epl'),
			'class'		=>	'col-1 epl-inner-div',
			'id'		=>	'epl-contact-add-listing-wrap',
			'help'		=>	__('' , 'epl') . '<hr/>',
			'fields'	=>	array(

				array(
					'name'		=>	'property_owner',
					'label'		=>	__('','epl'),
					'type'		=>	'hidden',
					'value'	    =>	$contact->id,
				),
				array(
					'name'		=>	'post_title',
					'label'		=>	__('Title','epl'),
					'type'		=>	'text',
				),
				array(
					'name'		=>	'property_address_lot_number',
					'label'		=>	__('Lot', 'epl'),
					'type'		=>	'text',
					'maxlength'	=>	'40',
					'include'	=>	array('land', 'commercial_land')
				),

				array(
					'name'		=>	'property_address_sub_number',
					'label'		=>	__('Unit', 'epl'),
					'type'		=>	'text',
					'maxlength'	=>	'40',
					'exclude'	=>	array('land', 'commercial_land')
				),

				array(
					'name'		=>	'property_address_street_number',
					'label'		=>	__('Street Number', 'epl'),
					'type'		=>	'text',
					'maxlength'	=>	'40'
				),

				array(
					'name'		=>	'property_address_street',
					'label'		=>	__('Street Name', 'epl'),
					'type'		=>	'text',
					'maxlength'	=>	'80'
				),

				array(
					'name'		=>	'property_address_suburb',
					'label'		=>	epl_labels('label_suburb'),
					'type'		=>	'text',
					'maxlength'	=>	'80'
				),

				array(
					'name'		=>	'property_address_state',
					'label'		=>	epl_labels('label_state'),
					'type'		=>	'text',
					'maxlength'	=>	'80'
				),

				array(
					'name'		=>	'property_address_postal_code',
					'label'		=>	epl_labels('label_postcode'),
					'type'		=>	'text',
					'maxlength'	=>	'30'
				),

				array(
					'name'		=>	'property_address_country',
					'label'		=>	__('Country', 'epl'),
					'type'		=>	'text',
					'maxlength'	=>	'40'
				),
				array(
					'name'		=>	'property_listing_type',
					'label'		=>	__('Listing Type','epl'),
					'type'		=>	'select',
					'class'     =>  'contact-note-select',
					'opts'      =>  $listing_types,
					'maxlength'	=>	'200',
				),
				array(
					'name'		=>	'property_listing_status',
					'label'		=>	__('Listing Status','epl'),
					'type'		=>	'select',
					'class'     =>  'contact-note-select',
					'opts'      => apply_filters('epl_contact_property_listing_status', array(
						'appraisal' =>  __('Appraisal','epl'),
						'new'       =>  __('New','epl'),
						'hot'       =>  __('Hot','epl'),
					)),
					'maxlength'	=>	'200',
				),

				array(
					'name'		=>	'contact_listing_submit',
					'value'		=>	__('Add','epl'),
					'class'     =>  'button button-primary',
					'type'		=>	'submit',
				),
			)
		),

	);
	$form_builder->set_form_attributes( array('name'    =>  'epl_contact_add_listing_form', 'id'    =>  'epl_contact_add_listing_form') );
	$form_builder->add_nonce('add_contact_listing_nonce');
	$form_builder->add_sections($fields);
	echo '<div class="epl-contact-add-listing-form-wrap">';
	$form_builder->render_form();
	echo '</div>';

}
add_action('epl_contact_add_listing_form','epl_contact_add_listing_form');

