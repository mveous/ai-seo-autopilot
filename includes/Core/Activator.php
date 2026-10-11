<?php
/**
 * Runs on plugin activation.
 *
 * @package Aasa\Core
 */

namespace Aasa\Core;

use Aasa\Admin\Settings;
use Aasa\Database\Migrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {

	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		require_once AASA_DIR . 'includes/Database/Migrator.php';
		Migrator::install();

		update_option( 'aasa_db_version', AASA_DB_VERSION );

		if ( false === get_option( 'aasa_settings', false ) ) {
			require_once AASA_DIR . 'includes/Admin/Settings.php';
			add_option( 'aasa_settings', Settings::defaults() );
		}

		if ( false === get_option( 'aasa_activated_at', false ) ) {
			add_option( 'aasa_activated_at', time() );
		}

		// Sitemap/robots rely on rewrite rules; flush once on activation.
		update_option( 'aasa_flush_rewrite', 1 );
	}
}
