<?php
/**
 * @package AISEOAutopilot\AI\Generators
 */

namespace AISEOAutopilot\AI\Generators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MetaDescriptionGenerator extends AbstractGenerator {

	/**
	 * @return string|\WP_Error
	 */
	public function generate_for_post( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'ai_seo_autopilot_not_found', __( 'Post not found.', 'ai-seo-autopilot' ) );
		}

		$context = $this->build_post_context( $post );
		$bundle  = $this->prompts->for_meta_description( $context );
		$result  = $this->run( 'meta_description_generation', $bundle );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$description = $this->clean_text( $result->content );

		if ( function_exists( 'mb_strlen' ) && mb_strlen( $description ) > 165 ) {
			$description = mb_substr( $description, 0, 165 );
		}

		return $description;
	}
}
