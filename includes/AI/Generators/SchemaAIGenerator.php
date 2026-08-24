<?php
/**
 * AI-assisted basic schema generation (Free tier). Restricted to simple,
 * low-risk content types — Product, Event, Recipe, FAQ, HowTo,
 * LocalBusiness and other rich types are Pro's Advanced Schema module,
 * which adds its own validation on top of this.
 *
 * @package AISEOAutopilot\AI\Generators
 */

namespace AISEOAutopilot\AI\Generators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SchemaAIGenerator extends AbstractGenerator {

	private const ALLOWED_TYPES = array( 'Article', 'BlogPosting', 'WebPage' );

	/**
	 * @return array<string,mixed>|\WP_Error
	 */
	public function generate_for_post( int $post_id, string $schema_type = 'Article' ) {
		if ( ! in_array( $schema_type, self::ALLOWED_TYPES, true ) ) {
			$schema_type = 'Article';
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'ai_seo_autopilot_not_found', __( 'Post not found.', 'ai-seo-autopilot' ) );
		}

		$context                = $this->build_post_context( $post );
		$context['schema_type'] = $schema_type;

		$bundle = $this->prompts->for_basic_schema( $context );
		$result = $this->run( 'schema_generation', $bundle );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$json = $result->json();
		if ( null === $json ) {
			return new \WP_Error( 'ai_seo_autopilot_invalid_ai_response', __( 'The AI response could not be parsed as schema JSON.', 'ai-seo-autopilot' ) );
		}

		$clean         = $this->sanitize_deep( $json );
		$clean['@type'] = $schema_type;

		if ( empty( $clean['url'] ) ) {
			$clean['url'] = (string) get_permalink( $post );
		}

		return $clean;
	}
}
