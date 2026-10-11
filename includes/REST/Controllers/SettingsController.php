<?php
/**
 * GET /aasa/v1/settings — read-only settings snapshot for editor UI.
 * Writes go through the classic admin-post form handlers in
 * Admin\Settings, which additionally validate each AI provider key
 * against the provider itself before saving. Nothing sensitive (API
 * keys are stored in a separate, never-exposed option) is included here.
 *
 * @package Aasa\REST\Controllers
 */

namespace Aasa\REST\Controllers;

use Aasa\Admin\Settings;
use Aasa\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsController extends AbstractController {

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => $this->permission( 'edit_seo_meta' ),
			)
		);
	}

	public function get_settings(): \WP_REST_Response {
		/** @var Settings|null $settings */
		$settings  = $this->plugin->get( 'admin.settings' );
		$providers = $this->plugin->get( 'ai.providers' );

		return $this->success(
			array(
				'settings'      => $settings ? $settings->get_all() : array(),
				'ai_configured' => $providers ? $providers->is_configured() : false,
			)
		);
	}
}
