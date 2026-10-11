<?php
/**
 * POST /aasa/v1/ai/generate — the single entry point the editor UI
 * uses for every AI-assisted generation feature. Dispatches to the
 * matching generator; every generator enforces the usage limit and
 * sanitizes AI output before it's ever returned to the browser.
 *
 * @package Aasa\REST\Controllers
 */

namespace Aasa\REST\Controllers;

use Aasa\AI\Generators\AltTextGenerator;
use Aasa\AI\Generators\KeywordSuggester;
use Aasa\AI\Generators\MetaDescriptionGenerator;
use Aasa\AI\Generators\SchemaAIGenerator;
use Aasa\AI\Generators\TitleGenerator;
use Aasa\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIController extends AbstractController {

	private const ALLOWED_FEATURES = array( 'title', 'meta_description', 'alt_text', 'keywords', 'schema' );

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/ai/generate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => $this->permission( 'use_ai_generation' ),
				'args'                => array(
					'feature'       => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => self::ALLOWED_FEATURES,
					),
					'post_id'       => array( 'sanitize_callback' => 'absint' ),
					'attachment_id' => array( 'sanitize_callback' => 'absint' ),
					'schema_type'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	public function generate( \WP_REST_Request $request ): \WP_REST_Response {
		$feature = (string) $request->get_param( 'feature' );
		$post_id = (int) $request->get_param( 'post_id' );

		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->error( new \WP_Error( 'forbidden', __( 'You cannot edit this content.', 'ai-autopilot-seo-assistant' ) ), 403 );
		}

		$providers = $this->plugin->get( 'ai.providers' );
		$prompts   = $this->plugin->get( 'ai.prompts' );
		$usage     = $this->plugin->get( 'ai.usage' );

		if ( ! $providers || ! $prompts || ! $usage ) {
			return $this->error( new \WP_Error( 'unavailable', __( 'AI services are unavailable.', 'ai-autopilot-seo-assistant' ) ), 500 );
		}

		$result = match ( $feature ) {
			'title'            => ( new TitleGenerator( $providers, $prompts, $usage ) )->generate_for_post( $post_id ),
			'meta_description' => ( new MetaDescriptionGenerator( $providers, $prompts, $usage ) )->generate_for_post( $post_id ),
			'keywords'         => ( new KeywordSuggester( $providers, $prompts, $usage ) )->generate_for_post( $post_id ),
			'schema'           => ( new SchemaAIGenerator( $providers, $prompts, $usage ) )->generate_for_post( $post_id, (string) $request->get_param( 'schema_type' ) ),
			'alt_text'         => $this->generate_alt_text( $request, $providers, $prompts, $usage ),
			default            => new \WP_Error( 'aasa_unknown_feature', __( 'Unknown AI feature.', 'ai-autopilot-seo-assistant' ) ),
		};

		if ( is_wp_error( $result ) ) {
			return $this->error( $result, 422 );
		}

		return $this->success( array( 'result' => $result ) );
	}

	private function generate_alt_text( \WP_REST_Request $request, $providers, $prompts, $usage ) {
		$attachment_id = (int) $request->get_param( 'attachment_id' );

		if ( ! $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot edit this attachment.', 'ai-autopilot-seo-assistant' ) );
		}

		$context_post_id = (int) $request->get_param( 'post_id' );

		return ( new AltTextGenerator( $providers, $prompts, $usage ) )->generate_for_attachment( $attachment_id, $context_post_id );
	}
}
