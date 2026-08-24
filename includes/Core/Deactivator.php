<?php
/**
 * Runs on plugin deactivation. Deliberately non-destructive: data,
 * options and tables are preserved so re-activating restores state.
 * Permanent removal only happens in uninstall.php, and only if the
 * user opted into it.
 *
 * @package AISEOAutopilot\Core
 */

namespace AISEOAutopilot\Core;

use AISEOAutopilot\Cron\Scheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {

	public static function deactivate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		wp_clear_scheduled_hook( Scheduler::HOOK_SITE_SCAN );

		update_option( 'ai_seo_autopilot_flush_rewrite', 1 );
	}
}
