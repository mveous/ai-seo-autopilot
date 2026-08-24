<?php
/**
 * Runs on plugin activation.
 *
 * @package AISEOAutopilot\Core
 */

namespace AISEOAutopilot\Core;

use AISEOAutopilot\Admin\Settings;
use AISEOAutopilot\Database\Migrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {

	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		require_once AI_SEO_AUTOPILOT_DIR . 'includes/Database/Migrator.php';
		Migrator::install();

		update_option( 'ai_seo_autopilot_db_version', AI_SEO_AUTOPILOT_DB_VERSION );

		if ( false === get_option( 'ai_seo_autopilot_settings', false ) ) {
			require_once AI_SEO_AUTOPILOT_DIR . 'includes/Admin/Settings.php';
			add_option( 'ai_seo_autopilot_settings', Settings::defaults() );
		}

		if ( false === get_option( 'ai_seo_autopilot_activated_at', false ) ) {
			add_option( 'ai_seo_autopilot_activated_at', time() );
		}

		// Sitemap/robots rely on rewrite rules; flush once on activation.
		update_option( 'ai_seo_autopilot_flush_rewrite', 1 );
	}
}
