<?php
/**
 * @package AISEOAutopilot\AI\Generators
 */

namespace AISEOAutopilot\AI\Generators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AltTextGenerator extends AbstractGenerator {

	/**
	 * @return string|\WP_Error
	 */
	public function generate_for_attachment( int $attachment_id, int $context_post_id = 0 ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new \WP_Error( 'ai_seo_autopilot_not_found', __( 'Image not found.', 'ai-seo-autopilot' ) );
		}

		$file      = get_attached_file( $attachment_id );
		$file_name = $file ? wp_basename( $file ) : $attachment->post_title;

		$context = array(
			'file_name' => $file_name,
		);

		if ( $context_post_id ) {
			$post = get_post( $context_post_id );
			if ( $post ) {
				$context['content_title']        = get_the_title( $post );
				$context['surrounding_context']  = wp_trim_words( wp_strip_all_tags( $post->post_content ), 80 );
			}
		}

		$bundle = $this->prompts->for_alt_text( $context );
		$result = $this->run( 'alt_text_generation', $bundle );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$alt = $this->clean_text( $result->content );

		if ( function_exists( 'mb_strlen' ) && mb_strlen( $alt ) > 125 ) {
			$alt = mb_substr( $alt, 0, 125 );
		}

		return $alt;
	}
}
