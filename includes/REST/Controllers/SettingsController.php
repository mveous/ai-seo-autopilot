<?php
/**
 * GET /ai-seo/v1/settings — read-only settings snapshot for editor UI.
 * Writes go through the classic admin-post form handlers in
 * Admin\Settings, which additionally validate each AI provider key
 * against the provider itself before saving. Nothing sensitive (API
 * keys are stored in a separate, never-exposed option) is included here.
 *
 * @package AISEOAutopilot\REST\Controllers
 */

namespace AISEOAutopilot\REST\Controllers;

use AISEOAutopilot\Admin\Settings;
use AISEOAutopilot\Core\Plugin;

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
		$features  = $this->plugin->get( 'features' );

		return $this->success(
			array(
				'settings'      => $settings ? $settings->get_all() : array(),
				'ai_configured' => $providers ? $providers->is_configured() : false,
				'pro_active'    => $features ? $features->pro_active() : false,
			)
		);
	}
}
