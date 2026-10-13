<?php
/**
 * Uninstall handler. Only runs when the plugin is deleted from the
 * Plugins screen (not on simple deactivation).
 *
 * Data is only removed if the user explicitly opted in via the
 * "Remove all data on uninstall" setting, to avoid silently destroying
 * SEO metadata, scan history and activity logs.
 *
 * @package Scvx
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$scvx_settings    = get_option( 'scvx_settings', array() );
$scvx_remove_data = ! empty( $scvx_settings['advanced']['remove_data_on_uninstall'] );

if ( ! $scvx_remove_data ) {
	return;
}

global $wpdb;

$scvx_tables = array(
	$wpdb->prefix . 'scvx_scans',
	$wpdb->prefix . 'scvx_issues',
	$wpdb->prefix . 'scvx_actions',
	$wpdb->prefix . 'scvx_activity',
	$wpdb->prefix . 'scvx_index',
	$wpdb->prefix . 'scvx_keywords',
	$wpdb->prefix . 'scvx_redirects',
	$wpdb->prefix . 'scvx_ai_usage',
);

foreach ( $scvx_tables as $scvx_table ) {
	// Table names are built from a fixed, hardcoded list above — never from
	// user input — so this is safe without a placeholder-based prepare().
	$wpdb->query( "DROP TABLE IF EXISTS `{$scvx_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- $scvx_table is from the fixed, hardcoded list above, never user input; dropping the plugin's own tables is uninstall.php's entire purpose, gated behind the opt-in "remove data on uninstall" setting checked above.
}

$scvx_options = array(
	'scvx_settings',
	'scvx_db_version',
	'scvx_activated_at',
	'scvx_flush_rewrite',
);

foreach ( $scvx_options as $scvx_option ) {
	delete_option( $scvx_option );
	delete_site_option( $scvx_option );
}

// Remove plugin-owned postmeta.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk-deleting the plugin's own postmeta by LIKE pattern; no core API exists for pattern-based postmeta deletion.
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_scvx_' ) . '%'
	)
);

wp_cache_flush();
