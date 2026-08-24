<?php
/**
 * Basic breadcrumb trail: home → taxonomy/parent chain → current item.
 * Exposed as a shortcode and a template tag for theme integration.
 *
 * @package AISEOAutopilot\SEO\Breadcrumbs
 */

namespace AISEOAutopilot\SEO\Breadcrumbs;

use AISEOAutopilot\Core\Registrable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BreadcrumbsGenerator implements Registrable {

	public function register(): void {
		add_shortcode( 'ai_seo_breadcrumbs', array( $this, 'shortcode' ) );
	}

	/**
	 * @return array<int,array{label:string,url:string}>
	 */
	public function get_trail(): array {
		$trail = array(
			array(
				'label' => __( 'Home', 'ai-seo-autopilot' ),
				'url'   => home_url( '/' ),
			),
		);

		if ( is_front_page() ) {
			return $trail;
		}

		if ( is_singular() ) {
			$post = get_queried_object();

			if ( $post instanceof \WP_Post ) {
				if ( 'page' === $post->post_type && $post->post_parent ) {
					$ancestors = array_reverse( get_post_ancestors( $post ) );
					foreach ( $ancestors as $ancestor_id ) {
						$trail[] = array(
							'label' => get_the_title( $ancestor_id ),
							'url'   => (string) get_permalink( $ancestor_id ),
						);
					}
				} elseif ( 'post' === $post->post_type ) {
					$categories = get_the_category( $post->ID );
					if ( ! empty( $categories ) ) {
						$trail[] = array(
							'label' => $categories[0]->name,
							'url'   => (string) get_category_link( $categories[0] ),
						);
					}
				} else {
					$archive_link = get_post_type_archive_link( $post->post_type );
					if ( $archive_link ) {
						$post_type_obj = get_post_type_object( $post->post_type );
						$trail[]       = array(
							'label' => $post_type_obj ? $post_type_obj->labels->name : $post->post_type,
							'url'   => $archive_link,
						);
					}
				}

				$trail[] = array(
					'label' => get_the_title( $post ),
					'url'   => '',
				);
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$trail[] = array(
					'label' => $term->name,
					'url'   => '',
				);
			}
		} elseif ( is_post_type_archive() ) {
			$trail[] = array(
				'label' => post_type_archive_title( '', false ),
				'url'   => '',
			);
		} elseif ( is_search() ) {
			$trail[] = array(
				/* translators: %s: search query */
				'label' => sprintf( __( 'Search results for "%s"', 'ai-seo-autopilot' ), get_search_query() ),
				'url'   => '',
			);
		} elseif ( is_404() ) {
			$trail[] = array(
				'label' => __( 'Page not found', 'ai-seo-autopilot' ),
				'url'   => '',
			);
		}

		/**
		 * Filters the final breadcrumb trail.
		 *
		 * @param array<int,array{label:string,url:string}> $trail
		 */
		return (array) apply_filters( 'ai_seo_autopilot/breadcrumbs_trail', $trail );
	}

	public function render(): void {
		$trail = $this->get_trail();
		if ( count( $trail ) < 2 ) {
			return;
		}

		echo '<nav class="ai-seo-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'ai-seo-autopilot' ) . '">';
		$last = count( $trail ) - 1;

		foreach ( $trail as $index => $item ) {
			if ( $index > 0 ) {
				echo '<span class="ai-seo-breadcrumbs__sep"> › </span>';
			}

			if ( '' !== $item['url'] && $index !== $last ) {
				printf( '<a href="%s">%s</a>', esc_url( $item['url'] ), esc_html( $item['label'] ) );
			} else {
				printf( '<span aria-current="page">%s</span>', esc_html( $item['label'] ) );
			}
		}

		echo '</nav>';
	}

	public function shortcode(): string {
		ob_start();
		$this->render();

		return (string) ob_get_clean();
	}
}
