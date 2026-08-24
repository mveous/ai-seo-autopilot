<?php
/**
 * GET /ai-seo/v1/schema/{post_id} — preview the schema.org JSON-LD graph
 * that would be output for a post, used by the editor sidebar.
 *
 * @package AISEOAutopilot\REST\Controllers
 */

namespace AISEOAutopilot\REST\Controllers;

use AISEOAutopilot\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SchemaController extends AbstractController {

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/schema/(?P<post_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'preview' ),
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

	public function preview( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->error( new \WP_Error( 'forbidden', __( 'You cannot edit this content.', 'ai-seo-autopilot' ) ), 403 );
		}

		$schema = $this->plugin->get( 'schema.generator' );
		if ( ! $schema ) {
			return $this->error( new \WP_Error( 'unavailable', __( 'Schema generator is unavailable.', 'ai-seo-autopilot' ) ), 500 );
		}

		return $this->success(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $schema->preview( $post_id ),
			)
		);
	}
}
