<?php
/**
 * Runs on plugin activation.
 *
 * @package Scvx\Core
 */

namespace Scvx\Core;

use Scvx\Admin\Settings;
use Scvx\Database\Migrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {

	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		require_once SCVX_DIR . 'includes/Database/Migrator.php';
		Migrator::install();

		update_option( 'scvx_db_version', SCVX_DB_VERSION );

		if ( false === get_option( 'scvx_settings', false ) ) {
			require_once SCVX_DIR . 'includes/Admin/Settings.php';
			add_option( 'scvx_settings', Settings::defaults() );
		}

		if ( false === get_option( 'scvx_activated_at', false ) ) {
			add_option( 'scvx_activated_at', time() );
		}

		// Sitemap/robots rely on rewrite rules; flush once on activation.
		update_option( 'scvx_flush_rewrite', 1 );
	}
}
