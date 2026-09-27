<?php
/**
 * Enqueues CSS/JS for the plugin's own admin screens only — never
 * globally on every wp-admin page.
 *
 * @package AISEOAutopilot\Admin
 */

namespace AISEOAutopilot\Admin;

use AISEOAutopilot\Core\Registrable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets implements Registrable {

	private const HANDLE = 'ai-seo-autopilot-admin';

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( string $hook ): void {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			AI_SEO_AUTOPILOT_URL . 'admin/css/admin.css',
			array(),
			AI_SEO_AUTOPILOT_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			AI_SEO_AUTOPILOT_URL . 'admin/js/admin.js',
			array( 'wp-i18n' ),
			AI_SEO_AUTOPILOT_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			'aiSeoAutopilot',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'ai-seo/v1' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'canManage' => current_user_can( 'manage_options' ),
				'i18n'      => array(
					'confirmRunScan' => __( 'Run a full SEO scan now? This runs in the background and may take a few minutes on large sites.', 'ai-seo-autopilot' ),
					'genericError'   => __( 'Something went wrong. Please try again.', 'ai-seo-autopilot' ),
					'fixing'         => __( 'Fixing…', 'ai-seo-autopilot' ),
					'fixed'          => __( 'Fixed', 'ai-seo-autopilot' ),
					'newTitle'       => __( 'New title:', 'ai-seo-autopilot' ),
					'newDescription' => __( 'New description:', 'ai-seo-autopilot' ),
				),
			)
		);
	}

	private function is_plugin_screen( string $hook ): bool {
		return false !== strpos( $hook, 'ai-seo-autopilot' );
	}
}
