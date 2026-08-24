<?php
/**
 * @package AISEOAutopilot\AI\Generators
 */

namespace AISEOAutopilot\AI\Generators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KeywordSuggester extends AbstractGenerator {

	private const ALLOWED_INTENTS   = array( 'informational', 'transactional', 'navigational', 'commercial' );
	private const ALLOWED_PRIORITIES = array( 'high', 'medium', 'low' );

	/**
	 * @return array<int,array{keyword:string,intent:string,priority:string}>|\WP_Error
	 */
	public function generate_for_post( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'ai_seo_autopilot_not_found', __( 'Post not found.', 'ai-seo-autopilot' ) );
		}

		$context = $this->build_post_context( $post );
		$bundle  = $this->prompts->for_keyword_suggestions( $context );
		$result  = $this->run( 'keyword_suggestions', $bundle );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$json = $result->json();
		if ( null === $json || empty( $json['keywords'] ) || ! is_array( $json['keywords'] ) ) {
			return new \WP_Error( 'ai_seo_autopilot_invalid_ai_response', __( 'The AI response could not be parsed as keyword suggestions.', 'ai-seo-autopilot' ) );
		}

		$keywords = array();

		foreach ( array_slice( $json['keywords'], 0, 10 ) as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['keyword'] ) ) {
				continue;
			}

			$intent   = strtolower( (string) ( $entry['intent'] ?? 'informational' ) );
			$priority = strtolower( (string) ( $entry['priority'] ?? 'medium' ) );

			$keywords[] = array(
				'keyword'  => sanitize_text_field( (string) $entry['keyword'] ),
				'intent'   => in_array( $intent, self::ALLOWED_INTENTS, true ) ? $intent : 'informational',
				'priority' => in_array( $priority, self::ALLOWED_PRIORITIES, true ) ? $priority : 'medium',
			);
		}

		return $keywords;
	}
}
