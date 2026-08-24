<?php
/**
 * Shared plumbing for AI content generators: usage-limit enforcement,
 * running the request, and recording which feature it was for.
 *
 * @package AISEOAutopilot\AI\Generators
 */

namespace AISEOAutopilot\AI\Generators;

use AISEOAutopilot\AI\AIProviderManager;
use AISEOAutopilot\AI\AIResponse;
use AISEOAutopilot\AI\PromptManager;
use AISEOAutopilot\AI\UsageTracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractGenerator {

	protected AIProviderManager $providers;

	protected PromptManager $prompts;

	protected UsageTracker $usage;

	public function __construct( AIProviderManager $providers, PromptManager $prompts, UsageTracker $usage ) {
		$this->providers = $providers;
		$this->prompts   = $prompts;
		$this->usage     = $usage;
	}

	/**
	 * @param array{system:string,user:string,options:array<string,mixed>} $prompt_bundle
	 *
	 * @return AIResponse|\WP_Error
	 */
	protected function run( string $feature, array $prompt_bundle ) {
		$allowed = $this->usage->can_make_request();
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		return $this->providers->generate(
			$feature,
			$prompt_bundle['system'],
			$prompt_bundle['user'],
			$prompt_bundle['options']
		);
	}

	/**
	 * Strips surrounding quote marks and stray whitespace/newlines the
	 * model sometimes adds even when told not to.
	 */
	protected function clean_text( string $text ): string {
		$text = trim( $text );
		$text = trim( $text, "\"'“”\n\r\t " );

		return sanitize_text_field( $text );
	}

	/**
	 * @return array<string,string>
	 */
	protected function build_post_context( \WP_Post $post ): array {
		/** @var \AISEOAutopilot\SEO\Metadata\MetaRepository|null $repository */
		$repository    = \AISEOAutopilot\Core\Plugin::instance()->get( 'seo.meta_repository' );
		$focus_keyword = $repository ? (string) $repository->get( $post->ID, 'focus_keyword' ) : '';

		$excerpt = '' !== $post->post_excerpt ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 60 );

		return array(
			'content_title'  => get_the_title( $post ),
			'excerpt'        => $excerpt,
			'body'           => wp_strip_all_tags( $post->post_content ),
			'focus_keyword'  => $focus_keyword,
			'site_name'      => get_bloginfo( 'name' ),
			'url'            => (string) get_permalink( $post ),
			'author'         => get_the_author_meta( 'display_name', $post->post_author ),
			'published_date' => get_post_time( 'c', true, $post ),
			'modified_date'  => get_post_modified_time( 'c', true, $post ),
		);
	}

	/**
	 * Recursively strip tags from every string leaf in a decoded JSON
	 * structure. Defense-in-depth for AI output that will be stored and
	 * later rendered (e.g. as schema JSON-LD) — never trust it verbatim.
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	protected function sanitize_deep( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'sanitize_deep' ), $value );
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( wp_strip_all_tags( $value ) );
		}

		return $value;
	}
}
