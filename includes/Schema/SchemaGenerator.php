<?php
/**
 * Basic schema.org JSON-LD output: WebPage, Article/BlogPosting,
 * Organization, and BreadcrumbList. Advanced types (Product, Event,
 * Recipe, FAQ, HowTo, LocalBusiness, …) are a Pro feature — see
 * FeatureManager::is_available( 'advanced_schema' ).
 *
 * A per-post AI-generated override (`_ai_seo_schema_json`) is honored
 * verbatim when present — it was already validated as well-formed JSON
 * at write time by MetaRepository — otherwise a safe default graph is
 * built from known, trusted post data only.
 *
 * @package AISEOAutopilot\Schema
 */

namespace AISEOAutopilot\Schema;

use AISEOAutopilot\Admin\Settings;
use AISEOAutopilot\Compatibility\SeoPluginDetector;
use AISEOAutopilot\Core\Plugin;
use AISEOAutopilot\Core\Registrable;
use AISEOAutopilot\SEO\Breadcrumbs\BreadcrumbsGenerator;
use AISEOAutopilot\SEO\Metadata\MetaRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SchemaGenerator implements Registrable {

	private MetaRepository $repository;

	private ?SeoPluginDetector $detector = null;

	public function __construct( MetaRepository $repository ) {
		$this->repository = $repository;
	}

	public function register(): void {
		/** @var SeoPluginDetector|null $detector */
		$this->detector = Plugin::instance()->get( 'compat.seo_plugins' );

		add_action( 'wp_head', array( $this, 'output' ), 5 );
	}

	public function output(): void {
		if ( null !== $this->detector && ! $this->detector->controls( 'schema' ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$graph = $this->build_graph_for_post( $post_id );
		if ( empty( $graph ) ) {
			return;
		}

		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		if ( false === $json ) {
			return;
		}

		// wp_json_encode already produces safe JSON; script tag content is
		// additionally hardened against premature </script> closure.
		printf(
			"<script type=\"application/ld+json\">%s</script>\n",
			str_replace( '</', '<\\/', $json ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() output for a JSON-LD script tag; esc_html() would corrupt the JSON.
		);
	}

	/**
	 * Public preview of the schema graph that would be output for a post
	 * right now — used by the editor sidebar and the REST schema endpoint.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function preview( int $post_id ): array {
		return $this->build_graph_for_post( $post_id );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function build_graph_for_post( int $post_id ): array {
		$override = (string) $this->repository->get( $post_id, 'schema_json' );

		if ( '' !== $override ) {
			$decoded = json_decode( $override, true );
			if ( is_array( $decoded ) ) {
				return $this->normalize_graph( $decoded );
			}
		}

		return $this->build_default_graph( $post_id );
	}

	/**
	 * Accept either a single node or an already-built @graph array.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_graph( array $decoded ): array {
		if ( isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] ) ) {
			return $decoded['@graph'];
		}

		if ( isset( $decoded['@type'] ) ) {
			return array( $decoded );
		}

		return array_values( $decoded );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function build_default_graph( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$settings = get_option( Settings::OPTION, array() );
		$schema   = $settings['schema'] ?? array();
		$url      = (string) get_permalink( $post );

		$organization = array(
			'@type' => $schema['organization_type'] ?? 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => $schema['organization_name'] ?? get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);
		if ( ! empty( $schema['organization_logo'] ) ) {
			$organization['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => esc_url_raw( $schema['organization_logo'] ),
			);
		}

		$webpage = array(
			'@type'    => 'WebPage',
			'@id'      => $url . '#webpage',
			'url'      => $url,
			'name'     => get_the_title( $post ),
			'isPartOf' => array( '@id' => home_url( '/#website' ) ),
			'inLanguage' => get_bloginfo( 'language' ),
		);

		$graph = array( $organization, $webpage );

		if ( 'post' === $post->post_type || post_type_supports( $post->post_type, 'excerpt' ) ) {
			$article_type = 'post' === $post->post_type ? 'BlogPosting' : 'Article';

			$article = array(
				'@type'         => $article_type,
				'@id'           => $url . '#article',
				'headline'      => wp_strip_all_tags( get_the_title( $post ) ),
				'description'   => wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 30 ),
				'datePublished' => get_post_time( 'c', true, $post ),
				'dateModified'  => get_post_modified_time( 'c', true, $post ),
				'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
				'author'        => array(
					'@type' => 'Person',
					'name'  => get_the_author_meta( 'display_name', $post->post_author ),
				),
				'publisher'     => array( '@id' => home_url( '/#organization' ) ),
			);

			$thumbnail_id = get_post_thumbnail_id( $post );
			if ( $thumbnail_id ) {
				$image = wp_get_attachment_image_src( $thumbnail_id, 'large' );
				if ( $image ) {
					$article['image'] = array(
						'@type'  => 'ImageObject',
						'url'    => $image[0],
						'width'  => $image[1],
						'height' => $image[2],
					);
				}
			}

			$graph[] = $article;
		}

		/** @var BreadcrumbsGenerator|null $breadcrumbs */
		$breadcrumbs = Plugin::instance()->get( 'seo.breadcrumbs' );
		if ( $breadcrumbs ) {
			$trail = $breadcrumbs->get_trail();
			if ( count( $trail ) > 1 ) {
				$items = array();
				foreach ( $trail as $index => $item ) {
					$list_item = array(
						'@type'    => 'ListItem',
						'position' => $index + 1,
						'name'     => $item['label'],
					);
					if ( '' !== $item['url'] ) {
						$list_item['item'] = $item['url'];
					}
					$items[] = $list_item;
				}

				$graph[] = array(
					'@type'           => 'BreadcrumbList',
					'@id'             => $url . '#breadcrumb',
					'itemListElement' => $items,
				);
			}
		}

		/**
		 * Filters the default schema @graph before output. Pro uses this
		 * to inject advanced schema types (Product, Event, LocalBusiness…).
		 *
		 * @param array<int,array<string,mixed>> $graph
		 * @param int                             $post_id
		 */
		return (array) apply_filters( 'ai_seo_autopilot/schema_graph', $graph, $post_id );
	}
}
