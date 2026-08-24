<?php
/**
 * POST /ai-seo/v1/analyze — live SEO analysis for a single post, used by
 * the block editor sidebar.
 *
 * @package AISEOAutopilot\REST\Controllers
 */

namespace AISEOAutopilot\REST\Controllers;

use AISEOAutopilot\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AnalyzeController extends AbstractController {

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/analyze',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'analyze' ),
				'permission_callback' => $this->permission( 'edit_seo_meta' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function analyze( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->error( new \WP_Error( 'forbidden', __( 'You cannot edit this content.', 'ai-seo-autopilot' ) ), 403 );
		}

		$analyzer = $this->plugin->get( 'seo.analyzer' );
		if ( ! $analyzer ) {
			return $this->error( new \WP_Error( 'unavailable', __( 'SEO analyzer is unavailable.', 'ai-seo-autopilot' ) ), 500 );
		}

		return $this->success( $analyzer->analyze_post( $post_id ) );
	}
}
