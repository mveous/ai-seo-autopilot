<?php
/**
 * POST /ai-seo/v1/scan — run one batch of the site audit.
 * GET  /ai-seo/v1/issues — list open issues.
 * POST /ai-seo/v1/issues/{id}/fix — fix a title/description issue with AI.
 *
 * @package AISEOAutopilot\REST\Controllers
 */

namespace AISEOAutopilot\REST\Controllers;

use AISEOAutopilot\AI\Generators\MetaDescriptionGenerator;
use AISEOAutopilot\AI\Generators\TitleGenerator;
use AISEOAutopilot\Core\Plugin;
use AISEOAutopilot\SEO\Audit\SiteAuditor;

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

		register_rest_route(
			self::NAMESPACE,
			'/issues/(?P<id>\d+)/fix',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'fix_issue' ),
				'permission_callback' => $this->permission( 'use_ai_generation' ),
				'args'                => array(
					'id' => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);
	}

	/**
	 * Generate a replacement title/description with AI, save it to the
	 * post's SEO meta, and mark the issue resolved.
	 */
	public function fix_issue( \WP_REST_Request $request ): \WP_REST_Response {
		$auditor    = $this->plugin->get( 'seo.auditor' );
		$repository = $this->plugin->get( 'seo.meta_repository' );
		$providers  = $this->plugin->get( 'ai.providers' );
		$prompts    = $this->plugin->get( 'ai.prompts' );
		$usage      = $this->plugin->get( 'ai.usage' );

		if ( ! $auditor || ! $repository || ! $providers || ! $prompts || ! $usage ) {
			return $this->error( new \WP_Error( 'unavailable', __( 'AI services are unavailable.', 'ai-seo-autopilot' ) ), 500 );
		}

		$issue = $auditor->get_issue( (int) $request->get_param( 'id' ) );
		if ( ! $issue || 'open' !== $issue['status'] ) {
			return $this->error( new \WP_Error( 'not_found', __( 'Issue not found or already resolved.', 'ai-seo-autopilot' ) ), 404 );
		}

		$field   = SiteAuditor::ai_fix_field( (string) $issue['issue_type'] );
		$post_id = (int) $issue['object_id'];

		if ( ! $field || ! $post_id ) {
			return $this->error( new \WP_Error( 'not_fixable', __( 'This issue cannot be fixed automatically.', 'ai-seo-autopilot' ) ), 422 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->error( new \WP_Error( 'forbidden', __( 'You cannot edit this content.', 'ai-seo-autopilot' ) ), 403 );
		}

		$generator = 'title' === $field
			? new TitleGenerator( $providers, $prompts, $usage )
			: new MetaDescriptionGenerator( $providers, $prompts, $usage );

		$value = $generator->generate_for_post( $post_id );

		if ( is_wp_error( $value ) ) {
			return $this->error( $value, 422 );
		}

		if ( '' === $value ) {
			return $this->error( new \WP_Error( 'empty_result', __( 'The AI returned an empty result. Please try again.', 'ai-seo-autopilot' ) ), 422 );
		}

		$repository->update( $post_id, array( $field => $value ) );
		$auditor->resolve_issue( (int) $issue['id'] );

		return $this->success(
			array(
				'field' => $field,
				'value' => $value,
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
