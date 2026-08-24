<?php
/**
 * POST /ai-seo/v1/scan — run one batch of the site audit.
 * GET  /ai-seo/v1/issues — list open issues.
 *
 * @package AISEOAutopilot\REST\Controllers
 */

namespace AISEOAutopilot\REST\Controllers;

use AISEOAutopilot\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScanController extends AbstractController {

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/scan',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_scan' ),
				'permission_callback' => $this->permission( 'run_scan' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/issues',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_issues' ),
				'permission_callback' => $this->permission( 'view_dashboard' ),
				'args'                => array(
					'per_page' => array(
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function run_scan( \WP_REST_Request $request ): \WP_REST_Response {
		$auditor = $this->plugin->get( 'seo.auditor' );

		if ( ! $auditor ) {
			return $this->error( new \WP_Error( 'unavailable', __( 'Site auditor is unavailable.', 'ai-seo-autopilot' ) ), 500 );
		}

		$result = $auditor->run_batch( 50 );

		return $this->success( $result );
	}

	public function get_issues( \WP_REST_Request $request ): \WP_REST_Response {
		$auditor = $this->plugin->get( 'seo.auditor' );

		if ( ! $auditor ) {
			return $this->error( new \WP_Error( 'unavailable', __( 'Site auditor is unavailable.', 'ai-seo-autopilot' ) ), 500 );
		}

		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );

		$issues = $auditor->get_open_issues( $per_page, ( $page - 1 ) * $per_page );

		return $this->success( array( 'issues' => $issues ) );
	}
}
