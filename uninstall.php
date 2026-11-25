<?php
/**
 * Removes tables, options and cron events when the plugin is deleted.
 *
 * @package WPIntegrations\KlaviyoConnector
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Both editions share this data. Bail on an installed Pro copy, not just an
 * active one: a bulk delete can remove this plugin while Pro is momentarily
 * deactivated, taking Pro's tokens and integrations with it.
 */
$wpi_klaviyo_pro = 'wpintegrations-connector-for-klaviyo-pro/wpintegrations-connector-for-klaviyo-pro.php';

$wpi_klaviyo_active = (array) get_option( 'active_plugins', array() );
$wpi_klaviyo_shared = in_array( $wpi_klaviyo_pro, $wpi_klaviyo_active, true )
	|| ( is_multisite() && array_key_exists( $wpi_klaviyo_pro, (array) get_site_option( 'active_sitewide_plugins', array() ) ) )
	|| file_exists( WP_PLUGIN_DIR . '/' . $wpi_klaviyo_pro );

if ( $wpi_klaviyo_shared ) {
	return;
}

global $wpdb;

// Plugin tables. Names are constants, not user input.
foreach ( array( 'wpi_klaviyo_flows', 'wpi_klaviyo_logs', 'wpi_klaviyo_jobs' ) as $wpi_klaviyo_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup of plugin-owned tables.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $wpi_klaviyo_table ) );
}

// Options.
delete_option( 'wpi_klaviyo_settings' );
delete_option( 'wpi_klaviyo_version' );
delete_option( 'wpi_klaviyo_migrations' );
delete_option( 'wpi_klaviyo_recent_shapes' );

// Transients: each form plugin's scan cache, plus Klaviyo list
// and field caches swept by prefix (they are keyed by a hash of the API key).
foreach ( array( 'elementor', 'cf7', 'wpforms', 'fluentform', 'gravityforms' ) as $wpi_klaviyo_trigger ) {
	delete_transient( 'wpi_klaviyo_' . $wpi_klaviyo_trigger . '_forms' );
}
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_wpi\_klaviyo\_api\_%'
	    OR option_name LIKE '\_transient\_timeout\_wpi\_klaviyo\_api\_%'"
);

// Cron events.
wp_clear_scheduled_hook( 'wpi_klaviyo_queue_tick' );
wp_clear_scheduled_hook( 'wpi_klaviyo_daily_purge' );
