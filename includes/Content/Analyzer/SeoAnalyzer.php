<?php
/**
 * On-page SEO analysis for a single post: title/description length,
 * focus-keyword usage, heading structure, content length, image ALT
 * coverage, internal linking, and basic readability. Produces a 0-100
 * score plus a checklist of pass/warn/fail results.
 *
 * @package AISEOAutopilot\Content\Analyzer
 */

namespace AISEOAutopilot\Content\Analyzer;

use AISEOAutopilot\Core\Plugin;
use AISEOAutopilot\SEO\Metadata\MetaRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SeoAnalyzer {

	private ContentScanner $scanner;

	public function __construct() {
		$this->scanner = new ContentScanner();
	}

	/**
	 * @return array{
	 *     score:int,
	 *     breakdown: array<string,int>,
	 *     checks: array<int,array{id:string,status:string,message:string}>
	 * }
	 */
	public function analyze_post( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array(
				'score'     => 0,
				'breakdown' => array(),
				'checks'    => array(),
			);
		}

		/** @var MetaRepository|null $repository */
		$repository = Plugin::instance()->get( 'seo.meta_repository' );

		$title       = $repository ? (string) $repository->get( $post_id, 'title' ) : '';
		$title       = '' !== $title ? $title : get_the_title( $post );
		$description = $repository ? (string) $repository->get( $post_id, 'description' ) : '';
		$keyword     = $repository ? (string) $repository->get( $post_id, 'focus_keyword' ) : '';

		$html      = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- invoking WordPress core's own filter, not defining a new hook.
		$structure = $this->scanner->analyze( $html );

		$checks     = array();
		$breakdown  = array();

		$breakdown['title']       = $this->score_title( $title, $keyword, $checks );
		$breakdown['description'] = $this->score_description( $description, $keyword, $checks );
		$breakdown['keyword']     = $this->score_keyword_usage( $keyword, $structure, $post, $checks );
		$breakdown['headings']    = $this->score_headings( $structure, $checks );
		$breakdown['content']     = $this->score_content_length( $structure, $checks );
		$breakdown['images']      = $this->score_images( $structure, $checks );
		$breakdown['links']       = $this->score_links( $structure, $checks );
		$breakdown['readability'] = $this->score_readability( $html, $structure, $checks );

		$weights = array(
			'title'       => 15,
			'description' => 15,
			'keyword'     => 15,
			'headings'    => 10,
			'content'     => 20,
			'images'      => 10,
			'links'       => 5,
			'readability' => 10,
		);

		$total  = 0;
		$weight_sum = 0;
		foreach ( $weights as $key => $weight ) {
			$total      += ( $breakdown[ $key ] ?? 0 ) * $weight;
			$weight_sum += $weight;
		}

		$score = $weight_sum > 0 ? (int) round( $total / $weight_sum ) : 0;

		return array(
			'score'     => max( 0, min( 100, $score ) ),
			'breakdown' => $breakdown,
			'checks'    => $checks,
		);
	}

	private function add_check( array &$checks, string $id, string $status, string $message ): void {
		$checks[] = array(
			'id'      => $id,
			'status'  => $status,
			'message' => $message,
		);
	}

	private function score_title( string $title, string $keyword, array &$checks ): int {
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );

		if ( '' === $title ) {
			$this->add_check( $checks, 'title_missing', 'bad', __( 'No SEO title is set.', 'ai-seo-autopilot' ) );
			return 0;
		}

		$score = 100;

		if ( $len < 30 || $len > 65 ) {
			$score -= 30;
			$this->add_check( $checks, 'title_length', 'warning', __( 'Title length is outside the recommended 50-60 characters.', 'ai-seo-autopilot' ) );
		} else {
			$this->add_check( $checks, 'title_length', 'good', __( 'Title length looks good.', 'ai-seo-autopilot' ) );
		}

		if ( '' !== $keyword && false === stripos( $title, $keyword ) ) {
			$score -= 30;
			$this->add_check( $checks, 'title_keyword', 'warning', __( 'Focus keyword is missing from the title.', 'ai-seo-autopilot' ) );
		} elseif ( '' !== $keyword ) {
			$this->add_check( $checks, 'title_keyword', 'good', __( 'Focus keyword found in the title.', 'ai-seo-autopilot' ) );
		}

		return max( 0, $score );
	}

	private function score_description( string $description, string $keyword, array &$checks ): int {
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $description ) : strlen( $description );

		if ( '' === $description ) {
			$this->add_check( $checks, 'description_missing', 'bad', __( 'No meta description is set.', 'ai-seo-autopilot' ) );
			return 0;
		}

		$score = 100;

		if ( $len < 120 || $len > 165 ) {
			$score -= 30;
			$this->add_check( $checks, 'description_length', 'warning', __( 'Meta description length is outside the recommended 140-160 characters.', 'ai-seo-autopilot' ) );
		} else {
			$this->add_check( $checks, 'description_length', 'good', __( 'Meta description length looks good.', 'ai-seo-autopilot' ) );
		}

		if ( '' !== $keyword && false === stripos( $description, $keyword ) ) {
			$score -= 30;
			$this->add_check( $checks, 'description_keyword', 'warning', __( 'Focus keyword is missing from the meta description.', 'ai-seo-autopilot' ) );
		}

		return max( 0, $score );
	}

	/**
	 * @param array<string,mixed> $structure
	 */
	private function score_keyword_usage( string $keyword, array $structure, \WP_Post $post, array &$checks ): int {
		if ( '' === $keyword ) {
			$this->add_check( $checks, 'keyword_missing', 'warning', __( 'No focus keyword is set.', 'ai-seo-autopilot' ) );
			return 50; // Neutral — not every page needs one, but we can't score usage.
		}

		$score        = 0;
		$plain        = wp_strip_all_tags( $post->post_content );
		$first_100    = function_exists( 'mb_substr' ) ? mb_substr( $plain, 0, 300 ) : substr( $plain, 0, 300 );

		if ( false !== stripos( $first_100, $keyword ) ) {
			$score += 40;
			$this->add_check( $checks, 'keyword_intro', 'good', __( 'Focus keyword appears early in the content.', 'ai-seo-autopilot' ) );
		} else {
			$this->add_check( $checks, 'keyword_intro', 'warning', __( 'Focus keyword does not appear in the first paragraph.', 'ai-seo-autopilot' ) );
		}

		$in_heading = false;
		foreach ( $structure['headings'] as $heading ) {
			if ( false !== stripos( $heading['text'], $keyword ) ) {
				$in_heading = true;
				break;
			}
		}
		if ( $in_heading ) {
			$score += 30;
			$this->add_check( $checks, 'keyword_heading', 'good', __( 'Focus keyword appears in a heading.', 'ai-seo-autopilot' ) );
		} else {
			$this->add_check( $checks, 'keyword_heading', 'warning', __( 'Focus keyword does not appear in any heading.', 'ai-seo-autopilot' ) );
		}

		$density_count = substr_count( strtolower( $plain ), strtolower( $keyword ) );
		$word_count    = max( 1, $structure['word_count'] );
		$density       = ( $density_count / $word_count ) * 100;

		if ( $density >= 0.5 && $density <= 2.5 ) {
			$score += 30;
			$this->add_check( $checks, 'keyword_density', 'good', __( 'Keyword density is within a healthy range.', 'ai-seo-autopilot' ) );
		} else {
			$this->add_check( $checks, 'keyword_density', 'warning', __( 'Keyword density is too low or too high.', 'ai-seo-autopilot' ) );
		}

		return min( 100, $score );
	}

	/**
	 * @param array<string,mixed> $structure
	 */
	private function score_headings( array $structure, array &$checks ): int {
		$h1_count = count( array_filter( $structure['headings'], static fn( $h ) => 1 === $h['level'] ) );
		$has_h2   = count( array_filter( $structure['headings'], static fn( $h ) => 2 === $h['level'] ) ) > 0;

		$score = 100;

		if ( 0 === $h1_count ) {
			$score -= 40;
			$this->add_check( $checks, 'h1_missing', 'bad', __( 'No H1 heading found.', 'ai-seo-autopilot' ) );
		} elseif ( $h1_count > 1 ) {
			$score -= 20;
			$this->add_check( $checks, 'h1_multiple', 'warning', __( 'Multiple H1 headings found — use only one per page.', 'ai-seo-autopilot' ) );
		} else {
			$this->add_check( $checks, 'h1_single', 'good', __( 'Exactly one H1 heading found.', 'ai-seo-autopilot' ) );
		}

		if ( $structure['word_count'] > 300 && ! $has_h2 ) {
			$score -= 20;
			$this->add_check( $checks, 'h2_missing', 'warning', __( 'Longer content should be broken up with H2 subheadings.', 'ai-seo-autopilot' ) );
		}

		return max( 0, $score );
	}

	/**
	 * @param array<string,mixed> $structure
	 */
	private function score_content_length( array $structure, array &$checks ): int {
		$count = $structure['word_count'];

		if ( $count < 100 ) {
			$this->add_check( $checks, 'content_thin', 'bad', __( 'Content is very thin (under 100 words).', 'ai-seo-autopilot' ) );
			return 10;
		}
		if ( $count < 300 ) {
			$this->add_check( $checks, 'content_short', 'warning', __( 'Content is shorter than the recommended 300 words.', 'ai-seo-autopilot' ) );
			return 50;
		}

		$this->add_check( $checks, 'content_length', 'good', __( 'Content length looks good.', 'ai-seo-autopilot' ) );

		return 100;
	}

	/**
	 * @param array<string,mixed> $structure
	 */
	private function score_images( array $structure, array &$checks ): int {
		$images = $structure['images'];

		if ( empty( $images ) ) {
			return 100; // No images is not a failure.
		}

		$missing = count( array_filter( $images, static fn( $img ) => ! $img['has_alt'] ) );

		if ( 0 === $missing ) {
			$this->add_check( $checks, 'images_alt', 'good', __( 'All images have ALT text.', 'ai-seo-autopilot' ) );
			return 100;
		}

		$this->add_check(
			$checks,
			'images_alt',
			'warning',
			sprintf(
				/* translators: %d: number of images missing alt text */
				__( '%d image(s) are missing ALT text.', 'ai-seo-autopilot' ),
				$missing
			)
		);

		return (int) max( 0, round( 100 - ( $missing / count( $images ) ) * 100 ) );
	}

	/**
	 * @param array<string,mixed> $structure
	 */
	private function score_links( array $structure, array &$checks ): int {
		$internal = count( array_filter( $structure['links'], static fn( $l ) => $l['internal'] ) );

		if ( $internal > 0 ) {
			$this->add_check( $checks, 'internal_links', 'good', __( 'Content includes internal links.', 'ai-seo-autopilot' ) );
			return 100;
		}

		$this->add_check( $checks, 'internal_links_missing', 'warning', __( 'No internal links found in this content.', 'ai-seo-autopilot' ) );

		return 40;
	}

	/**
	 * A lightweight, dependency-free readability heuristic based on
	 * average sentence length and average word length — not a full
	 * Flesch-Kincaid implementation, but a useful directional signal.
	 *
	 * @param array<string,mixed> $structure
	 */
	private function score_readability( string $html, array $structure, array &$checks ): int {
		$text = trim( wp_strip_all_tags( $html ) );

		if ( '' === $text ) {
			return 50;
		}

		$sentences      = max( 1, preg_match_all( '/[.!?]+/', $text ) );
		$words          = max( 1, $structure['word_count'] );
		$avg_sentence   = $words / $sentences;
		$avg_word_chars = ( function_exists( 'mb_strlen' ) ? mb_strlen( str_replace( ' ', '', $text ) ) : strlen( str_replace( ' ', '', $text ) ) ) / $words;

		$score = 100;

		if ( $avg_sentence > 25 ) {
			$score -= 30;
			$this->add_check( $checks, 'readability_sentences', 'warning', __( 'Sentences are long on average — consider shortening them.', 'ai-seo-autopilot' ) );
		} else {
			$this->add_check( $checks, 'readability_sentences', 'good', __( 'Sentence length looks reasonable.', 'ai-seo-autopilot' ) );
		}

		if ( $avg_word_chars > 6.5 ) {
			$score -= 20;
			$this->add_check( $checks, 'readability_words', 'warning', __( 'Vocabulary is complex on average — consider simpler words.', 'ai-seo-autopilot' ) );
		}

		return max( 0, $score );
	}
}
