<?php
/**
 * Uninstall handler. Only runs when the plugin is deleted from the
 * Plugins screen (not on simple deactivation).
 *
 * Data is only removed if the user explicitly opted in via the
 * "Remove all data on uninstall" setting, to avoid silently destroying
 * SEO metadata, scan history and activity logs.
 *
 * @package AISEOAutopilot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$ai_seo_autopilot_settings    = get_option( 'ai_seo_autopilot_settings', array() );
$ai_seo_autopilot_remove_data = ! empty( $ai_seo_autopilot_settings['advanced']['remove_data_on_uninstall'] );

if ( ! $ai_seo_autopilot_remove_data ) {
	return;
}

global $wpdb;

$ai_seo_autopilot_tables = array(
	$wpdb->prefix . 'ai_seo_scans',
	$wpdb->prefix . 'ai_seo_issues',
	$wpdb->prefix . 'ai_seo_actions',
	$wpdb->prefix . 'ai_seo_activity',
	$wpdb->prefix . 'ai_seo_index',
	$wpdb->prefix . 'ai_seo_keywords',
	$wpdb->prefix . 'ai_seo_redirects',
	$wpdb->prefix . 'ai_seo_ai_usage',
);

foreach ( $ai_seo_autopilot_tables as $ai_seo_autopilot_table ) {
	// Table names are built from a fixed, hardcoded list above — never from
	// user input — so this is safe without a placeholder-based prepare().
	$wpdb->query( "DROP TABLE IF EXISTS `{$ai_seo_autopilot_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- $ai_seo_autopilot_table is from the fixed, hardcoded list above, never user input; dropping the plugin's own tables is uninstall.php's entire purpose, gated behind the opt-in "remove data on uninstall" setting checked above.
}

$ai_seo_autopilot_options = array(
	'ai_seo_autopilot_settings',
	'ai_seo_autopilot_db_version',
	'ai_seo_autopilot_activated_at',
	'ai_seo_autopilot_flush_rewrite',
	'ai_seo_autopilot_license_key',
	'ai_seo_autopilot_license_status',
);

foreach ( $ai_seo_autopilot_options as $ai_seo_autopilot_option ) {
	delete_option( $ai_seo_autopilot_option );
	delete_site_option( $ai_seo_autopilot_option );
}

// Remove plugin-owned postmeta.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk-deleting the plugin's own postmeta by LIKE pattern; no core API exists for pattern-based postmeta deletion.
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_ai_seo_' ) . '%'
	)
);

wp_cache_flush();
