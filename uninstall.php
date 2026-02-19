<?php
/**
 * Color Me Beautiful – Uninstall Handler
 *
 * Executed automatically by WordPress when the plugin is deleted from the
 * Plugins screen (not just deactivated).  Removes all user meta written by
 * this plugin.
 *
 * Uses paginated WP_User_Query with an EXISTS meta_query filter so that only
 * users who actually saved a preference are iterated.  This is efficient even
 * on installations with tens of thousands of users.
 *
 * @package cm-beautiful
 * @since   1.0.0
 */

declare(strict_types=1);

// WordPress sets this constant before running uninstall.php.
// Exit immediately if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Meta keys written by this plugin.
 */
$cmb_meta_keys = [ 'cmb_ui_preset', 'cmb_ui_custom_accent', 'cmb_ui_night_mode' ];

/*
 * Paginate through users that have at least one of our meta keys so that
 * the loop terminates quickly on sites where most users never changed their
 * colour preference.
 */
$cmb_page     = 1;
$cmb_per_page = 100;

do {
	$user_query = new WP_User_Query(
		[
			/*
			 * Return only user IDs to avoid loading full WP_User objects and
			 * pulling unnecessary data from the database.
			 */
			'fields'     => 'ID',
			'number'     => $cmb_per_page,
			'paged'      => $cmb_page,

			/*
			 * Only match users who have at least one of our meta keys.
			 * On large installations this can reduce the query result set
			 * from hundreds of thousands of rows to just a handful.
			 */
			'meta_query' => [
				'relation' => 'OR',
				[
					'key'     => 'cmb_ui_preset',
					'compare' => 'EXISTS',
				],
				[
					'key'     => 'cmb_ui_custom_accent',
					'compare' => 'EXISTS',
				],
				[
					'key'     => 'cmb_ui_night_mode',
					'compare' => 'EXISTS',
				],
			],
		]
	);

	/** @var int[] $user_ids */
	$user_ids = $user_query->get_results();

	foreach ( $user_ids as $user_id ) {
		foreach ( $cmb_meta_keys as $meta_key ) {
			delete_user_meta( (int) $user_id, $meta_key );
		}
	}

	++$cmb_page;

	/*
	 * Continue while the previous page was full.  When the query returns
	 * fewer results than $cmb_per_page there are no more pages to process.
	 */
} while ( count( $user_ids ) === $cmb_per_page );
