<?php
/**
 * Central home for every AI prompt used by the plugin.
 *
 * No other class should build a raw prompt string — they call one of the
 * `for_*()` methods here and get back a {system, user} pair plus the
 * generation options (temperature, JSON mode, etc.) appropriate for that
 * feature. This keeps prompts versioned, reviewable in one place, and
 * consistently token-optimized (all free-form content is truncated
 * before injection).
 *
 * @package AISEOAutopilot\AI
 */

namespace AISEOAutopilot\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PromptManager {

	private const PROMPT_VERSION = '1';

	/**
	 * @return array{system:string,user:string,options:array<string,mixed>}
	 */
	public function for_title( array $context ): array {
		$system = $this->base_system_prompt() . "\n"
			. __( 'Write a single SEO title tag. 50-60 characters. No quotation marks. No clickbait. Reflect the page content and target keyword accurately.', 'ai-seo-autopilot' );

		$user = $this->render_context( $context, array( 'content_title', 'excerpt', 'focus_keyword', 'site_name' ) )
			. "\n\n" . __( 'Return only the title text, nothing else.', 'ai-seo-autopilot' );

		return array(
			'system'  => $system,
			'user'    => $user,
			'options' => array(
				'temperature' => 0.5,
				'max_tokens'  => 60,
				'json'        => false,
			),
		);
	}

	/**
	 * @return array{system:string,user:string,options:array<string,mixed>}
	 */
	public function for_meta_description( array $context ): array {
		$system = $this->base_system_prompt() . "\n"
			. __( 'Write a single SEO meta description. 140-160 characters. Active voice, includes the target keyword naturally, ends with a reason to click. No quotation marks.', 'ai-seo-autopilot' );

		$user = $this->render_context( $context, array( 'content_title', 'excerpt', 'focus_keyword', 'site_name' ) )
			. "\n\n" . __( 'Return only the meta description text, nothing else.', 'ai-seo-autopilot' );

		return array(
			'system'  => $system,
			'user'    => $user,
			'options' => array(
				'temperature' => 0.5,
				'max_tokens'  => 120,
				'json'        => false,
			),
		);
	}

	/**
	 * @return array{system:string,user:string,options:array<string,mixed>}
	 */
	public function for_alt_text( array $context ): array {
		$system = $this->base_system_prompt() . "\n"
			. __( 'Write concise, descriptive ALT text for an image, for accessibility and SEO. Describe what is visually in the image. Under 125 characters. No "image of" or "picture of" prefixes. No filler.', 'ai-seo-autopilot' );

		$parts = array();
		if ( ! empty( $context['file_name'] ) ) {
			$parts[] = sprintf( 'File name: %s', $this->truncate( (string) $context['file_name'], 120 ) );
		}
		if ( ! empty( $context['surrounding_context'] ) ) {
			$parts[] = sprintf( "Surrounding page context:\n%s", $this->truncate( (string) $context['surrounding_context'], 500 ) );
		}
		if ( ! empty( $context['content_title'] ) ) {
			$parts[] = sprintf( 'Page title: %s', $this->truncate( (string) $context['content_title'], 200 ) );
		}

		$user = implode( "\n", $parts ) . "\n\n" . __( 'Return only the ALT text, nothing else.', 'ai-seo-autopilot' );

		return array(
			'system'  => $system,
			'user'    => $user,
			'options' => array(
				'temperature' => 0.3,
				'max_tokens'  => 60,
				'json'        => false,
			),
		);
	}

	/**
	 * @return array{system:string,user:string,options:array<string,mixed>}
	 */
	public function for_keyword_suggestions( array $context ): array {
		$system = $this->base_system_prompt() . "\n"
			. __( 'Suggest relevant SEO keyword phrases for the given content. Mix short-tail and long-tail phrases. Favor realistic search intent over generic terms.', 'ai-seo-autopilot' );

		$user = $this->render_context( $context, array( 'content_title', 'excerpt', 'site_name' ) )
			. "\n\n" . __( 'Return strict JSON: {"keywords": [{"keyword": string, "intent": "informational|transactional|navigational|commercial", "priority": "high|medium|low"}]}. Suggest at most 10 keywords.', 'ai-seo-autopilot' );

		return array(
			'system'  => $system,
			'user'    => $user,
			'options' => array(
				'temperature' => 0.4,
				'max_tokens'  => 500,
				'json'        => true,
			),
		);
	}

	/**
	 * @return array{system:string,user:string,options:array<string,mixed>}
	 */
	public function for_basic_schema( array $context ): array {
		$type   = $context['schema_type'] ?? 'Article';
		$system = $this->base_system_prompt() . "\n"
			. sprintf(
				/* translators: %s: schema.org type */
				__( 'Generate schema.org JSON-LD of type "%s" for the given content. Use only fields you can support from the provided context — never invent facts, prices, ratings, or reviews.', 'ai-seo-autopilot' ),
				$type
			);

		$user = $this->render_context( $context, array( 'content_title', 'excerpt', 'url', 'author', 'published_date', 'modified_date', 'site_name' ) )
			. "\n\n" . __( 'Return strict JSON representing only the value of "@graph" or the schema object itself — no markdown fences, no commentary.', 'ai-seo-autopilot' );

		return array(
			'system'  => $system,
			'user'    => $user,
			'options' => array(
				'temperature' => 0.2,
				'max_tokens'  => 700,
				'json'        => true,
			),
		);
	}

	/**
	 * @return array{system:string,user:string,options:array<string,mixed>}
	 */
	public function for_content_analysis( array $context ): array {
		$system = $this->base_system_prompt() . "\n"
			. __( 'You are analyzing on-page SEO. Identify missing topics, weak structure, and search-intent mismatches. Be specific and actionable, not generic.', 'ai-seo-autopilot' );

		$user = $this->render_context( $context, array( 'content_title', 'body', 'focus_keyword' ) )
			. "\n\n" . __( 'Return strict JSON: {"summary": string, "issues": [string], "recommendations": [string]}. Limit to the 5 most important issues and 5 most important recommendations.', 'ai-seo-autopilot' );

		return array(
			'system'  => $system,
			'user'    => $user,
			'options' => array(
				'temperature' => 0.3,
				'max_tokens'  => 700,
				'json'        => true,
			),
		);
	}

	private function base_system_prompt(): string {
		$site_name = get_bloginfo( 'name' );
		$language  = get_bloginfo( 'language' );

		$prompt = sprintf(
			/* translators: 1: site name, 2: content language, 3: prompt version */
			__( "You are the AI SEO assistant inside the AI SEO Autopilot WordPress plugin, working on the website \"%1\$s\". Respond in the site's content language (%2\$s). Be precise, factual, and never invent information not present in the provided context. [prompt v%3\$s]", 'ai-seo-autopilot' ),
			$site_name,
			$language,
			self::PROMPT_VERSION
		);

		/**
		 * Filters the base system prompt injected into every AI request.
		 *
		 * @param string $prompt
		 */
		return (string) apply_filters( 'ai_seo_autopilot/base_system_prompt', $prompt );
	}

	/**
	 * Render a whitelist of context keys as a compact, labeled block,
	 * truncating any free-form text fields to keep token usage low.
	 *
	 * @param array<string,mixed> $context
	 * @param string[]            $keys
	 */
	private function render_context( array $context, array $keys ): string {
		$limits = array(
			'body'    => 4000,
			'excerpt' => 800,
		);

		$lines = array();

		foreach ( $keys as $key ) {
			if ( empty( $context[ $key ] ) ) {
				continue;
			}

			$value = (string) $context[ $key ];
			$limit = $limits[ $key ] ?? 300;
			$value = $this->truncate( $value, $limit );

			$lines[] = sprintf( '%s: %s', ucwords( str_replace( '_', ' ', $key ) ), $value );
		}

		return implode( "\n", $lines );
	}

	private function truncate( string $text, int $max_chars ): string {
		$text = wp_strip_all_tags( $text );

		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $max_chars ) {
			return mb_substr( $text, 0, $max_chars ) . '…';
		}

		if ( strlen( $text ) > $max_chars ) {
			return substr( $text, 0, $max_chars ) . '…';
		}

		return $text;
	}
}
