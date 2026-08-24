<?php
/**
 * GET /ai-seo/v1/dashboard — summary data for the admin dashboard.
 *
 * @package AISEOAutopilot\REST\Controllers
 */

namespace AISEOAutopilot\REST\Controllers;

use AISEOAutopilot\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DashboardController extends AbstractController {

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/dashboard',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => $this->permission( 'view_dashboard' ),
			)
		);
	}

	public function get_dashboard( \WP_REST_Request $request ): \WP_REST_Response {
		$auditor  = $this->plugin->get( 'seo.auditor' );
		$usage    = $this->plugin->get( 'ai.usage' );
		$providers = $this->plugin->get( 'ai.providers' );

		return $this->success(
			array(
				'summary'        => $auditor ? $auditor->get_latest_summary() : null,
				'scan_in_progress' => $auditor ? $auditor->is_scan_in_progress() : false,
				'ai_usage'       => $usage ? $usage->get_summary( 30 ) : null,
				'ai_configured'  => $providers ? $providers->is_configured() : false,
			)
		);
	}
}
