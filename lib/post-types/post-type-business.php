<?php
/**
 * Register post type :: Business
 *
 * @package     EPL
 * @subpackage  Functions/CPT
 * @copyright   Copyright (c) 2019, Merv Barrett
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and sets up the Business custom post type.
 *
 * @since 1.0
 * @return void
 */
function epl_register_custom_post_type_business() {

	$archives = defined( 'EPL_BUSINESS_DISABLE_ARCHIVE' ) && EPL_BUSINESS_DISABLE_ARCHIVE ? false : true;
	$slug     = defined( 'EPL_BUSINESS_SLUG' ) ? EPL_BUSINESS_SLUG : 'business';
	$rewrite  = defined( 'EPL_BUSINESS_DISABLE_REWRITE' ) && EPL_BUSINESS_DISABLE_REWRITE ? false : array(
		'slug'       => $slug,
		'with_front' => false,
	);
	$rest     = defined( 'EPL_BUSINESS_DISABLE_REST' ) && EPL_BUSINESS_DISABLE_REST ? false : true;

	$labels = apply_filters(
		'epl_business_labels',
		array(
			'name'               => __( 'Business Listings', 'easy-property-listings' ),
			'singular_name'      => __( 'Business Listings', 'easy-property-listings' ),
			'menu_name'          => __( 'Business', 'easy-property-listings' ),
			'add_new'            => __( 'Add New', 'easy-property-listings' ),
			'add_new_item'       => __( 'Add New Business Listing', 'easy-property-listings' ),
			'edit_item'          => __( 'Edit Business Listing', 'easy-property-listings' ),
			'new_item'           => __( 'New Business Listing', 'easy-property-listings' ),
			'update_item'        => __( 'Update Business Listing', 'easy-property-listings' ),
			'all_items'          => __( 'All Business Listings', 'easy-property-listings' ),
			'view_item'          => __( 'View Business Listing', 'easy-property-listings' ),
			'search_items'       => __( 'Search Business Listing', 'easy-property-listings' ),
			'not_found'          => __( 'Business Listing Not Found', 'easy-property-listings' ),
			'not_found_in_trash' => __( 'Business Listing Not Found in Trash', 'easy-property-listings' ),
			'parent_item_colon'  => __( 'Parent Business Listing:', 'easy-property-listings' ),
		)
	);

	$business_args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => $rewrite,
		'menu_icon'          => 'dashicons-cart',
		'capability_type'    => 'post',
		'has_archive'        => $archives,
		'hierarchical'       => false,
		'menu_position'      => '26.6',
		'show_in_rest'       => $rest,
		'taxonomies'         => array( 'location', 'tax_feature' ),
		'supports'           => apply_filters( 'epl_business_supports', array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ) ),
	);
	epl_register_post_type( 'business', 'Business', apply_filters( 'epl_business_post_type_args', $business_args ) );
}
add_action( 'init', 'epl_register_custom_post_type_business', 0 );


/**
 * Manage Admin Business Post Type Columns.
 *
 * @since 1.0
 * @return void
 */
if ( is_admin() ) {
	/**
	 * Manage Admin Business Post Type Columns: Heading.
	 *
	 * @since 1.0
	 * @param array $columns Column array.
	 * @return $columns with epl_post_type_business_admin_columns filter.
	 */
	function epl_manage_business_columns_heading( $columns ) {

		$columns = array(
			'cb'                => '<input type="checkbox" />',
			'property_featured' => '<span class="dashicons dashicons-star-half"></span>' . '<span class="epl-manage-featured">' . __( 'Featured', 'easy-property-listings' ) . '</span>',
			'property_thumb'    => __( 'Image', 'easy-property-listings' ),
			'property_price'    => __( 'Price', 'easy-property-listings' ),
			'title'             => __( 'Address', 'easy-property-listings' ),
			'listing'           => __( 'Listing Details', 'easy-property-listings' ),
			'listing_id'        => __( 'Unique ID', 'easy-property-listings' ),
			'geo'               => __( 'Geo', 'easy-property-listings' ),
			'property_status'   => __( 'Status', 'easy-property-listings' ),
			'agent'             => __( 'Agent', 'easy-property-listings' ),
			'date'              => __( 'Date', 'easy-property-listings' ),
		) + $columns;

		// unset author columns as duplicate of agent column.
		unset( $columns['author'] );
		unset( $columns['comments'] );

		// Geocode Column.
		if ( 1 !== epl_get_option( 'debug', 0 ) ) {
			unset( $columns['geo'] );
		}

		// Listing ID Column.
		if ( 1 !== epl_get_option( 'admin_unique_id', 0 ) ) {
			unset( $columns['listing_id'] );
		}

		return apply_filters( 'epl_post_type_business_admin_columns', $columns );
	}
	add_filter( 'manage_edit-business_columns', 'epl_manage_business_columns_heading' );

	/**
	 * Manage Admin Business Post Type Columns: Row Contents.
	 *
	 * @since 1.0
	 * @param var $column column.
	 * @param int $post_id post id.
	 */
	function epl_manage_business_columns_value( $column, $post_id ) {
		global $post,$property;
		switch ( $column ) {

			// If displaying the 'Featured' image column.
			case 'property_featured':
				do_action( 'epl_manage_listing_column_featured' );

				break;

			// If displaying the 'Featured' image column.
			case 'property_thumb':
				do_action( 'epl_manage_listing_column_property_thumb' );

				break;

			case 'listing':


				do_action( 'epl_manage_listing_column_listing' );



				// Get the post meta.


				$category = get_post_meta( $post_id, 'property_commercial_category', true );

				$outgoings = get_post_meta( $post_id, 'property_com_outgoings', true );
				$return    = get_post_meta( $post_id, 'property_com_return', true );

				// property_bus_takings (number)
				// property_bus_franchise (y/n)
				// property_bus_terms (textarea)
				// property_com_return

				// return

				// <businessCategory id="1">
				//	<name>Food/Hospitality</name>
				//	<businessSubCategory>
				//	<name>Takeaway Food</name>

				//	</businessSubCategory>
				//		</businessCategory>
				//	<businessCategory id="2"/>
				//	<businessCategory id="3"/>



				if ( ! empty( $category ) ) {
					echo '<div class="epl_meta_category">Category: ' , $category , '</div>';
				}



				if ( ! empty( $outgoings ) ) {
					echo '<div class="epl_meta_outgoings">Outgoings: ' , epl_currency_formatted_amount( $outgoings ) , '</div>';
				}

				if ( ! empty( $return ) ) {
					echo '<div class="epl_meta_baths">Return: ' , $return , '%</div>';
				}



				do_action( 'epl_manage_business_listing_column_listing_details' );

				break;

			// If displaying the 'Listing ID' column.
			case 'listing_id':
				do_action( 'epl_manage_listing_column_listing_id' );

				break;

			// If displaying the 'Geocoding' column.
			case 'geo':
				do_action( 'epl_manage_listing_column_geo' );

				break;

			// If displaying the 'Price' column.
			case 'property_price':

				do_action( 'epl_manage_listing_column_price' );





				$price                = get_post_meta( $post_id, 'property_price', true );
				$view                 = get_post_meta( $post_id, 'property_price_view', true );
				$property_under_offer = get_post_meta( $post_id, 'property_under_offer', true );
				$lease                = get_post_meta( $post_id, 'property_com_rent', true );
				$lease_period         = get_post_meta( $post_id, 'property_com_rent_period', true );
				$lease_date           = get_post_meta( $post_id, 'property_com_lease_end_date', true );

				$max_price = (int) epl_get_option( 'epl_max_graph_sales_price', '2000000' );

				$property_status    = ucfirst( get_post_meta( $post_id, 'property_status', true ) );
				$property_authority = get_post_meta( $post_id, 'property_authority', true );
				$sold_price         = get_post_meta( $post_id, 'property_sold_price', true );

				if ( ! empty( $property_under_offer ) && 'yes' === $property_under_offer ) {
					$class = 'bar-under-offer';
				} elseif ( 'Current' === $property_status ) {
					$class = 'bar-home-open';
				} elseif ( 'Sold' === $property_status || 'Leased' === $property_status ) {
					$class = 'bar-home-sold';
				} else {
					$class = '';
				}
				if ( '' !== $sold_price ) {
					$barwidth = 0 === $max_price ? 0 : $sold_price / $max_price * 100;
				} else {
					$barwidth = 0 === $max_price ? 0 : $price / $max_price * 100;
				}
				echo '
					<div class="epl-price-bar ' . $class . '">
						<span style="width:' . $barwidth . '%"></span>
					</div>';

				if ( ! empty( $property_under_offer ) && 'yes' === $property_under_offer ) {
					echo '<div class="type_under_offer">' . epl_labels( 'label_under_offer' ) . '</div>';
				}

				if ( empty( $view ) ) {
					echo '<div class="epl_meta_search_price">' . __( 'Sale', 'easy-property-listings' ) . ': ' , epl_currency_formatted_amount( $price ), '</div>';
				} else {
					echo '<div class="epl_meta_price">' , $view , '</div>';
				}

				if ( ! empty( $lease ) ) {
					if ( empty( $lease_period ) ) {
						$lease_period = 'annual';
					}
					echo '<div class="epl_meta_lease_price">Lease: ' , epl_currency_formatted_amount( $lease ), ' ' ,epl_listing_load_meta_commercial_rent_period_value( $lease_period ) ,'</div>';
				}

				if ( ! empty( $lease_date ) ) {
					echo '<div class="epl_meta_lease_date">' . __( 'Lease End', 'easy-property-listings' ) . ': ' ,  $lease_date , '</div>';
				}
				if ( 'auction' === $property_authority ) {
					_e( 'Auction ', 'easy-property-listings' );

					echo '<br>' . $property->get_property_auction( true );
				}

				break;

			// If displaying the 'property_status' column.
			case 'property_status':
				do_action( 'epl_manage_listing_column_property_status' );

				break;

			// If displaying the 'agent' column.
			case 'agent':
				do_action( 'epl_manage_listing_column_agent' );

				break;

			// Just break out of the switch statement for everything else.
			default:
				break;
		}

	}
	add_action( 'manage_business_posts_custom_column', 'epl_manage_business_columns_value', 10, 2 );

	/**
	 * Manage Business Columns Sorting
	 *
	 * @since 1.0
	 * @param array $columns Column array.
	 */
	function epl_manage_business_sortable_columns( $columns ) {
		$columns['property_status'] = 'property_status';
		return $columns;
	}
	add_filter( 'manage_edit-business_sortable_columns', 'epl_manage_business_sortable_columns' );
}
