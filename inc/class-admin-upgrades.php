<?php
/**
 * Upgrades
 *
 * @package Sell Media
 * @author Thad Allender <support@graphpaperpress.com>
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class SellMediaAdminUpgrades {

	/**
	 * Constructor
	 */
	public function __construct() {

		// fires on plugin activation in install.php
		add_action( 'sell_media_upgrades', array( $this, 'upgrades' ), 10, 1 );

		// fix attachments hook
		add_action( 'sell_media_fix_attachments_event', array( $this, 'fix_attachments' ) );

		// Add new cron schedules
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );

	}

	/**
	 * Handles version comparisons and upgrades
	 */
	public function upgrades( $version ) {

		/**
		 * This script pulls the current settings for Sell Media and extensions, then grooms them as needed
		 * making them ready for the updated settings API.
		 */
		if ( $version <= '1.6.5' ) {

			global $wpdb;
			$current_settings = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->prefix}options WHERE option_name LIKE 'sell_media_%';" );

			if ( empty( $current_settings ) ) {
				return;
			}

			$new_settings = array();
			foreach ( $current_settings as $r ) {
				$serialized = maybe_unserialize( $r->option_value );
				if ( is_array( $serialized ) && ! empty( $serialized ) ) {
					foreach ( $serialized as $k => $v ) {
						if ( ! empty( $v ) ) {
							/**
							 * The legacy format wasn't saved in the same format of the
							 * new settings API, we update the format and take some time
							 * to prefix our options.
							 */
							if ( in_array( $k, array( 'show_collection', 'show_license', 'show_creators' ), true ) ) {
								$new_settings['admin_columns'][] = $k;
							} elseif ( 'image_url' === $k ) {
								unset( $k );
								$new_settings['watermark_attachment_url'] = $v;
							} elseif ( 'attachment_id' === $k ) {
								unset( $k );
								$new_settings['watermark_attachment_id'] = $v;
							} elseif ( 'all' === $k ) {
								unset( $k );
								$new_settings['watermark_all'][] = 'yes';
							} elseif ( 'sell_media_free_downloads' === $r->option_name && 'api_key' === $k ) {
								unset( $k );
								$new_settings['free_downloads_api_key'] = $v;
							} elseif ( 'sell_media_free_downloads' === $r->option_name && 'list' === $k ) {
								unset( $k );
								$new_settings['free_downloads_list'] = $v;
							} elseif ( 'api_key' === $k ) {
								unset( $k );
								$new_settings['mailchimp_api_key'] = $v;
							} elseif ( 'list' === $k ) {
								unset( $k );
								$new_settings['mailchimp_list'] = $v;
							} elseif ( 'hide_download_tab' === $k ) {
								unset( $k );
								$new_settings['reprints_hide_download_tabs'][] = 'yes';
							} elseif ( 'base_region' === $k ) {
								unset( $k );
								$new_settings['reprints_base_region'] = $v;
							} elseif ( 'unit_measurement' === $k ) {
								unset( $k );
								$new_settings['reprints_unit_measurement'] = $v;
							} else {
								$new_settings[ $k ] = $v;
							}
						}
					}
				}
			}
			$update_option_result = update_option( 'sell_media_options', $new_settings );
		}

		if ( $version <= '2.2.6' ) {
			/**
			 * Schedule an event that fires every minute to repair attachments in chunks
			 */
			if ( ! wp_next_scheduled ( 'sell_media_fix_attachments_event' ) ) {
				wp_schedule_event( time(), 'minute', 'sell_media_fix_attachments_event' );
			}
		}
	}

	/**
	 * Fix Attachments
	 *
	 * In version prior to 2.2.7, Sell Media would assign keywords of the attachment
	 * to the actual sell_media_item post type. This created problems and unnecessary
	 * code complexity when it came to searching for keywords.
	 *
	 * To fix this, we're scheduling an cron event that loops over sell_media_item
	 * post types in chunks of 10, gets all attachments of each entry, sets keywords from item,
	 * reads original file and sets keywords on attachments (was missing prior to v2.0.5),
	 * saves all iptc in a custom field, sets post_parent value of attachment to the sell_media_item id
	 * and finally sets a custom field on all attachments with the post_parent id.
	 *
	 * Now, searching is simplified and we can search attachment post types for keywords.
	 */
	public function fix_attachments() {

		// Create an option to hold the current offset value
		// This value gets increased by 10 every time this events runs
		// Until no more entries are found.
		$option_name = 'sell_media_fix_attachments';
		$offset = get_option( $option_name, 0 );

		// Query args
		$args = array(
			'post_type' => 'sell_media_item',
			'posts_per_page' => -1,
			'offset' => $offset,
		);

		// Query all sell_media_items
		$the_query = new WP_Query( $args );

		// The Loop
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();

				// the keywords assigned to the single sell_media_item entry
				$keyword_ids = wp_get_post_terms( get_the_ID(), 'keywords', array( 'fields' => 'ids' ) );

				// loop over all attachments saved to the single sell_media_item entry
				$attachments = sell_media_get_attachments( get_the_ID() );

				if ( $attachments ) {

					foreach ( $attachments as $attachment ) {

						// make sure keywords exist
						if ( ! is_wp_error( $keyword_ids ) ) {
							wp_set_object_terms( $attachment, $keyword_ids, 'keywords', true );
						}

						// Loop over all sell media attachments and parse/save iptc data
						// as both post meta and custom taxonomy terms.
						$original_file = get_attached_file( $attachment );
						if ( file_exists( $original_file ) ) {
							$image_products->parse_iptc_info( $original_file, $attachment );
						}

						// update the attachments post meta key with the sell_media_item id
						update_post_meta( $attachment, '_sell_media_for_sale_product_id', get_the_ID() );

						// Old attachments didn't have the post_parent of the sell_media_item set
						// so let's update it now since we check post_parent for search.
						$attachment_update = array(
							'ID' => $attachment,
							'post_parent' => get_the_ID(),
						);
						wp_update_post( $attachment_update );

					} // end foreach
				} // end attachments check
			} // end while
			// restore original post data
			wp_reset_postdata();
		} else {
			// no more entries, so let's clean up
			wp_clear_scheduled_hook( 'sell_media_fix_attachments' );
			delete_option( $option_name );
		}

		set_option( $option_name, $offset + 10 );
	}


	/**
	 * Add new cron schedules
	 * WP only includes a few cron schedules
	 * We need to add a new one for every minute to run our upgrades quickly
	 * 
	 * @param  array $array existing cron event array
	 * @return array $array our new cron events
	 */
	public function cron_schedules( $array ) {
		$array['minute'] = array(
				'interval' => 60,
				'display' => 'Once every minute',
		);
		return $array;
	}

}