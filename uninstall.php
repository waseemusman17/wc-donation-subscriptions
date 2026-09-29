<?php
/**
 * Uninstall routine for WooCommerce Donation Subscriptions.
 *
 * Only runs when the plugin is deleted from the Plugins screen (not on
 * deactivation), per WordPress.org guidelines. Removes the product meta
 * this plugin creates; no custom tables or options are created by this
 * plugin, so there is nothing else to clean up.
 *
 * @package WC_Donation_Subscriptions
 */

// Guard against direct access; WordPress defines this constant itself when
// it calls this file as part of plugin deletion.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete this plugin's product meta on a single site.
 */
function wc_donation_subscriptions_uninstall_cleanup() {
	global $wpdb;

	$meta_keys = array(
		'_is_donation_subscription',
		'_donation_minimum_amount',
		'_donation_description',
	);

	foreach ( $meta_keys as $meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ) );
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		wc_donation_subscriptions_uninstall_cleanup();
		restore_current_blog();
	}
} else {
	wc_donation_subscriptions_uninstall_cleanup();
}
