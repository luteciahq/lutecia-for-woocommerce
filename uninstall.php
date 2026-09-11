<?php
/**
 * Uninstall cleanup: removes every trace the plugin left in the database
 * and tells the hub to stop serving the store (best effort: the response
 * is not awaited, the local cleanup happens regardless).
 *
 * Order meta written by sales attribution (_lutecia_ref,
 * _lutecia_ref_reported, _lutecia_ref_attempts) is kept: it is part of the
 * order history.
 *
 * @package Lutecia\WC
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$lutecia_client_id = (string) get_option( 'lutecia_client_id', '' );
$lutecia_secret    = (string) get_option( 'lutecia_webhook_secret', '' );
if ( '' !== $lutecia_client_id && '' !== $lutecia_secret ) {
	$lutecia_body = wp_json_encode( array( 'client_id' => $lutecia_client_id ) );
	wp_remote_post(
		( defined( 'LUTECIA_WC_HUB_URL' ) ? LUTECIA_WC_HUB_URL : 'https://api.lutecia.app' ) . '/api/plugin/disconnect',
		array(
			'timeout'  => 5,
			'blocking' => false,
			'headers'  => array(
				'Content-Type'        => 'application/json',
				'X-Lutecia-Signature' => hash_hmac( 'sha256', $lutecia_body, $lutecia_secret ),
			),
			'body'     => $lutecia_body,
		)
	);
}

// The WooCommerce API key the plugin created for the hub.
$lutecia_key_id = (int) get_option( 'lutecia_wc_api_key_id', 0 );
if ( $lutecia_key_id > 0 ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- no CRUD API for WC API keys.
	$wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'key_id' => $lutecia_key_id ), array( '%d' ) );
}

delete_option( 'lutecia_client_id' );
delete_option( 'lutecia_webhook_secret' );
delete_option( 'lutecia_wc_api_key_id' );
delete_option( 'lutecia_connected_at' );
delete_option( 'lutecia_attribution_enabled' );
delete_option( 'lutecia_ucp_cache_salt' );
delete_option( 'lutecia_site_url' );
delete_option( 'lutecia_site_env' );
delete_option( 'lutecia_duplicate_site' );
delete_option( 'lutecia_terms_accepted' );

wp_clear_scheduled_hook( 'lutecia_notify_hub' );
wp_unschedule_hook( 'lutecia_report_conversion' );

// Cached discovery documents: keys are dynamic (salt and version suffix).
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- transient keys are dynamic.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_lutecia_ucp_manifest_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_lutecia_ucp_manifest_' ) . '%'
	)
);
