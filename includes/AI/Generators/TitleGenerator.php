<?php
/**
 * @package AISEOAutopilot\AI\Generators
 */

namespace AISEOAutopilot\AI\Generators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TitleGenerator extends AbstractGenerator {

	/**
	 * @return string|\WP_Error
	 */
	public function generate_for_post( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'ai_seo_autopilot_not_found', __( 'Post not found.', 'ai-seo-autopilot' ) );
		}

		$context = $this->build_post_context( $post );
		$bundle  = $this->prompts->for_title( $context );
		$result  = $this->run( 'title_generation', $bundle );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$title = $this->clean_text( $result->content );

		if ( function_exists( 'mb_strlen' ) && mb_strlen( $title ) > 70 ) {
			$title = mb_substr( $title, 0, 70 );
		}

		return $title;
	}
}
