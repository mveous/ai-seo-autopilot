<?php
/**
 * Parses rendered post HTML into structural facts (headings, images,
 * links, word count) that SeoAnalyzer and SiteAuditor both build on.
 * Pure, stateless, no I/O — safe to call as often as needed.
 *
 * @package AISEOAutopilot\Content\Analyzer
 */

namespace AISEOAutopilot\Content\Analyzer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContentScanner {

	/**
	 * @return array{
	 *     word_count:int,
	 *     paragraph_count:int,
	 *     headings: array<int,array{level:int,text:string}>,
	 *     images: array<int,array{src:string,alt:string,has_alt:bool}>,
	 *     links: array<int,array{href:string,text:string,internal:bool}>
	 * }
	 */
	public function analyze( string $html ): array {
		$plain_text = trim( wp_strip_all_tags( $html ) );
		$word_count = '' === $plain_text ? 0 : str_word_count( $plain_text );

		$result = array(
			'word_count'      => $word_count,
			'paragraph_count' => 0,
			'headings'        => array(),
			'images'          => array(),
			'links'           => array(),
		);

		if ( '' === trim( $html ) ) {
			return $result;
		}

		$dom = new \DOMDocument();
		$internal_errors = libxml_use_internal_errors( true );

		$dom->loadHTML(
			'<?xml encoding="utf-8" ?><div id="ai-seo-root">' . $html . '</div>',
			LIBXML_NOERROR | LIBXML_NOWARNING
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $internal_errors );

		$result['paragraph_count'] = $dom->getElementsByTagName( 'p' )->length;

		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $level => $tag ) {
			foreach ( $dom->getElementsByTagName( $tag ) as $node ) {
				$result['headings'][] = array(
					'level' => $level + 1,
					'text'  => trim( $node->textContent ),
				);
			}
		}

		$home = home_url();

		foreach ( $dom->getElementsByTagName( 'img' ) as $node ) {
			/** @var \DOMElement $node */
			$alt = $node->hasAttribute( 'alt' ) ? trim( $node->getAttribute( 'alt' ) ) : '';
			$result['images'][] = array(
				'src'     => $node->getAttribute( 'src' ),
				'alt'     => $alt,
				'has_alt' => '' !== $alt,
			);
		}

		foreach ( $dom->getElementsByTagName( 'a' ) as $node ) {
			/** @var \DOMElement $node */
			$href = $node->getAttribute( 'href' );
			if ( '' === $href || 0 === strpos( $href, '#' ) ) {
				continue;
			}

			$result['links'][] = array(
				'href'     => $href,
				'text'     => trim( $node->textContent ),
				'internal' => '' === $home || false !== strpos( $href, $home ) || 0 === strpos( $href, '/' ),
			);
		}

		return $result;
	}
}
