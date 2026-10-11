<?php
/**
 * Enqueues CSS/JS for the plugin's own admin screens only — never
 * globally on every wp-admin page.
 *
 * @package Aasa\Admin
 */

namespace Aasa\Admin;

use Aasa\Core\Registrable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets implements Registrable {

	private const HANDLE = 'ai-autopilot-seo-assistant-admin';

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( string $hook ): void {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			AASA_URL . 'admin/css/admin.css',
			array(),
			AASA_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			AASA_URL . 'admin/js/admin.js',
			array( 'wp-i18n' ),
			AASA_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			'aasa',
			array(
				'restUrl'       => esc_url_raw( rest_url( 'aasa/v1' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'canManage'     => current_user_can( 'manage_options' ),
				'adminPostUrl'  => esc_url_raw( admin_url( 'admin-post.php' ) ),
				'settingsNonce' => wp_create_nonce( 'aasa_settings' ),
				'i18n'          => array(
					'confirmRunScan'   => __( 'Run a full SEO scan now? This runs in the background and may take a few minutes on large sites.', 'ai-autopilot-seo-assistant' ),
					'genericError'     => __( 'Something went wrong. Please try again.', 'ai-autopilot-seo-assistant' ),
					'fixing'           => __( 'Fixing…', 'ai-autopilot-seo-assistant' ),
					'fixed'            => __( 'Fixed', 'ai-autopilot-seo-assistant' ),
					'newTitle'         => __( 'New title:', 'ai-autopilot-seo-assistant' ),
					'newDescription'   => __( 'New description:', 'ai-autopilot-seo-assistant' ),
					'confirmRemoveKey' => __( 'Remove this API key?', 'ai-autopilot-seo-assistant' ),
				),
			)
		);
	}

	private function is_plugin_screen( string $hook ): bool {
		return false !== strpos( $hook, 'ai-autopilot-seo-assistant' );
	}
}
