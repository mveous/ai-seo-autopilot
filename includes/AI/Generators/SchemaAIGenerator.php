<?php
/**
 * AI-assisted schema.org JSON-LD generation for a single post, for any
 * schema.org type the caller requests — the underlying prompt and AI
 * response are generic, not type-specific.
 *
 * @package Aasa\AI\Generators
 */

namespace Aasa\AI\Generators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SchemaAIGenerator extends AbstractGenerator {

	/**
	 * @return array<string,mixed>|\WP_Error
	 */
	public function generate_for_post( int $post_id, string $schema_type = 'Article' ) {
		$schema_type = $this->sanitize_schema_type( $schema_type );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'aasa_not_found', __( 'Post not found.', 'ai-autopilot-seo-assistant' ) );
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
			return new \WP_Error( 'aasa_invalid_ai_response', __( 'The AI response could not be parsed as schema JSON.', 'ai-autopilot-seo-assistant' ) );
		}

		$clean         = $this->sanitize_deep( $json );
		$clean['@type'] = $schema_type;

		if ( empty( $clean['url'] ) ) {
			$clean['url'] = (string) get_permalink( $post );
		}

		return $clean;
	}

	/**
	 * schema.org type names are a bare CamelCase token (e.g. "LocalBusiness");
	 * strip anything else so the value can't break the prompt or the
	 * resulting "@type" field.
	 */
	private function sanitize_schema_type( string $schema_type ): string {
		$schema_type = preg_replace( '/[^A-Za-z]/', '', $schema_type );

		return '' !== $schema_type ? $schema_type : 'Article';
	}
}
