<?php
/**
 * Uninstall for Polylang WooCommerce Bridge.
 *
 * @package Polylang_WooCommerce_Bridge
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'plwc_bridge_version' );
delete_option( 'plwc_bridge_needs_rewrite_flush' );
delete_option( 'plwc_bridge_switcher_enabled' );
delete_option( 'plwc_bridge_switcher_show_flags' );
delete_option( 'plwc_bridge_switcher_show_full_name' );

if ( is_multisite() ) {
	global $wpdb;

	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

	if ( is_array( $blog_ids ) ) {
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			delete_option( 'plwc_bridge_version' );
			delete_option( 'plwc_bridge_needs_rewrite_flush' );
			delete_option( 'plwc_bridge_switcher_enabled' );
			delete_option( 'plwc_bridge_switcher_show_flags' );
			delete_option( 'plwc_bridge_switcher_show_full_name' );
			restore_current_blog();
		}
	}
}
