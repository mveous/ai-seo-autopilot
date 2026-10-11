<?php
/**
 * Uninstall handler. Only runs when the plugin is deleted from the
 * Plugins screen (not on simple deactivation).
 *
 * Data is only removed if the user explicitly opted in via the
 * "Remove all data on uninstall" setting, to avoid silently destroying
 * SEO metadata, scan history and activity logs.
 *
 * @package Aasa
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$aasa_settings    = get_option( 'aasa_settings', array() );
$aasa_remove_data = ! empty( $aasa_settings['advanced']['remove_data_on_uninstall'] );

if ( ! $aasa_remove_data ) {
	return;
}

global $wpdb;

$aasa_tables = array(
	$wpdb->prefix . 'aasa_scans',
	$wpdb->prefix . 'aasa_issues',
	$wpdb->prefix . 'aasa_actions',
	$wpdb->prefix . 'aasa_activity',
	$wpdb->prefix . 'aasa_index',
	$wpdb->prefix . 'aasa_keywords',
	$wpdb->prefix . 'aasa_redirects',
	$wpdb->prefix . 'aasa_ai_usage',
);

foreach ( $aasa_tables as $aasa_table ) {
	// Table names are built from a fixed, hardcoded list above — never from
	// user input — so this is safe without a placeholder-based prepare().
	$wpdb->query( "DROP TABLE IF EXISTS `{$aasa_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- $aasa_table is from the fixed, hardcoded list above, never user input; dropping the plugin's own tables is uninstall.php's entire purpose, gated behind the opt-in "remove data on uninstall" setting checked above.
}

$aasa_options = array(
	'aasa_settings',
	'aasa_db_version',
	'aasa_activated_at',
	'aasa_flush_rewrite',
);

foreach ( $aasa_options as $aasa_option ) {
	delete_option( $aasa_option );
	delete_site_option( $aasa_option );
}

// Remove plugin-owned postmeta.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk-deleting the plugin's own postmeta by LIKE pattern; no core API exists for pattern-based postmeta deletion.
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_aasa_' ) . '%'
	)
);

wp_cache_flush();
