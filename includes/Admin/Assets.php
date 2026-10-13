<?php
/**
 * Enqueues CSS/JS for the plugin's own admin screens only — never
 * globally on every wp-admin page.
 *
 * @package Scvx\Admin
 */

namespace Scvx\Admin;

use Scvx\Core\Registrable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets implements Registrable {

	private const HANDLE = 'scanvexa-seo-admin';

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( string $hook ): void {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			SCVX_URL . 'admin/css/admin.css',
			array(),
			SCVX_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			SCVX_URL . 'admin/js/admin.js',
			array( 'wp-i18n' ),
			SCVX_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			'scvx',
			array(
				'restUrl'       => esc_url_raw( rest_url( 'scvx/v1' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'canManage'     => current_user_can( 'manage_options' ),
				'adminPostUrl'  => esc_url_raw( admin_url( 'admin-post.php' ) ),
				'settingsNonce' => wp_create_nonce( 'scvx_settings' ),
				'i18n'          => array(
					'confirmRunScan'   => __( 'Run a full SEO scan now? This runs in the background and may take a few minutes on large sites.', 'scanvexa-seo' ),
					'genericError'     => __( 'Something went wrong. Please try again.', 'scanvexa-seo' ),
					'fixing'           => __( 'Fixing…', 'scanvexa-seo' ),
					'fixed'            => __( 'Fixed', 'scanvexa-seo' ),
					'newTitle'         => __( 'New title:', 'scanvexa-seo' ),
					'newDescription'   => __( 'New description:', 'scanvexa-seo' ),
					'confirmRemoveKey' => __( 'Remove this API key?', 'scanvexa-seo' ),
				),
			)
		);
	}

	private function is_plugin_screen( string $hook ): bool {
		return false !== strpos( $hook, 'scanvexa-seo' );
	}
}
