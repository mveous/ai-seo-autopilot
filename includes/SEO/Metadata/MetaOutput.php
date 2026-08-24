<?php
/**
 * Frontend output: document title, meta description, canonical, robots
 * meta, Open Graph and Twitter Card tags. Lightweight by design — no
 * database writes, no AI calls, nothing synchronous beyond simple reads
 * of already-cached post data.
 *
 * Every output area is gated by SeoPluginDetector::controls() so this
 * never duplicates output alongside another active SEO plugin.
 *
 * @package AISEOAutopilot\SEO\Metadata
 */

namespace AISEOAutopilot\SEO\Metadata;

use AISEOAutopilot\Admin\Settings;
use AISEOAutopilot\Compatibility\SeoPluginDetector;
use AISEOAutopilot\Core\Plugin;
use AISEOAutopilot\Core\Registrable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MetaOutput implements Registrable {

	private MetaRepository $repository;

	private ?SeoPluginDetector $detector = null;

	public function __construct( MetaRepository $repository ) {
		$this->repository = $repository;
	}

	public function register(): void {
		/** @var SeoPluginDetector|null $detector */
		$detector       = Plugin::instance()->get( 'compat.seo_plugins' );
		$this->detector = $detector;

		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 15 );
		add_filter( 'wp_robots', array( $this, 'filter_robots' ) );
		add_filter( 'get_canonical_url', array( $this, 'filter_canonical' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'output_meta_description' ), 1 );
		add_action( 'wp_head', array( $this, 'output_social_meta' ), 2 );
	}

	private function controls( string $area ): bool {
		return null === $this->detector || $this->detector->controls( $area );
	}

	public function filter_document_title( string $title ): string {
		if ( ! $this->controls( 'titles' ) || ! is_singular() ) {
			return $title;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return $title;
		}

		$custom = (string) $this->repository->get( $post_id, 'title' );
		if ( '' !== $custom ) {
			return $custom;
		}

		$settings  = get_option( Settings::OPTION, array() );
		$separator = $settings['seo']['title_separator'] ?? '-';

		return sprintf( '%s %s %s', get_the_title( $post_id ), $separator, get_bloginfo( 'name' ) );
	}

	/**
	 * @param array<string,bool> $robots
	 *
	 * @return array<string,bool>
	 */
	public function filter_robots( array $robots ): array {
		$settings = get_option( Settings::OPTION, array() );
		$seo      = $settings['seo'] ?? array();

		if ( ! empty( $seo['noindex_paginated'] ) && is_paged() && ! is_singular() ) {
			$robots['noindex'] = true;
		}

		if ( ! empty( $seo['noindex_archives'] ) && ( is_date() || is_author() ) ) {
			$robots['noindex'] = true;
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();

			if ( $post_id ) {
				if ( $this->repository->get( $post_id, 'robots_noindex' ) ) {
					$robots['noindex'] = true;
					unset( $robots['index'] );
				}
				if ( $this->repository->get( $post_id, 'robots_nofollow' ) ) {
					$robots['nofollow'] = true;
					unset( $robots['follow'] );
				}
				if ( $this->repository->get( $post_id, 'robots_noarchive' ) ) {
					$robots['noarchive'] = true;
				}
			}
		}

		return $robots;
	}

	public function filter_canonical( string $canonical_url, \WP_Post $post ): string {
		if ( ! $this->controls( 'canonical' ) ) {
			return $canonical_url;
		}

		$custom = (string) $this->repository->get( $post->ID, 'canonical' );

		return '' !== $custom ? $custom : $canonical_url;
	}

	public function output_meta_description(): void {
		if ( ! $this->controls( 'meta' ) || ! is_singular() ) {
			return;
		}

		$post_id     = get_queried_object_id();
		$description = $this->resolve_description( $post_id );

		if ( '' === $description ) {
			return;
		}

		printf( "<meta name=\"description\" content=\"%s\" />\n", esc_attr( $description ) );
	}

	public function output_social_meta(): void {
		if ( ! $this->controls( 'opengraph' ) || ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$settings = get_option( Settings::OPTION, array() );
		$social   = $settings['social'] ?? array();

		$title       = (string) $this->repository->get( $post_id, 'og_title' );
		$title       = '' !== $title ? $title : get_the_title( $post_id );
		$description = (string) $this->repository->get( $post_id, 'og_description' );
		$description = '' !== $description ? $description : $this->resolve_description( $post_id );
		$image       = (string) $this->repository->get( $post_id, 'og_image' );

		if ( '' === $image && has_post_thumbnail( $post_id ) ) {
			$image = (string) get_the_post_thumbnail_url( $post_id, 'large' );
		}
		if ( '' === $image && ! empty( $social['default_og_image'] ) ) {
			$image = (string) $social['default_og_image'];
		}

		$tags = array(
			'og:type'        => is_singular( 'post' ) ? 'article' : 'website',
			'og:title'       => $title,
			'og:description' => $description,
			'og:url'         => (string) get_permalink( $post_id ),
			'og:site_name'   => get_bloginfo( 'name' ),
		);
		if ( '' !== $image ) {
			$tags['og:image'] = $image;
		}

		foreach ( $tags as $property => $value ) {
			if ( '' === $value ) {
				continue;
			}
			printf( "<meta property=\"%s\" content=\"%s\" />\n", esc_attr( $property ), esc_attr( $value ) );
		}

		$twitter_title       = (string) $this->repository->get( $post_id, 'twitter_title' );
		$twitter_description = (string) $this->repository->get( $post_id, 'twitter_description' );
		$twitter_image       = (string) $this->repository->get( $post_id, 'twitter_image' );

		$twitter_tags = array(
			'twitter:card'        => '' !== $image || '' !== $twitter_image ? 'summary_large_image' : 'summary',
			'twitter:title'       => '' !== $twitter_title ? $twitter_title : $title,
			'twitter:description' => '' !== $twitter_description ? $twitter_description : $description,
		);
		if ( ! empty( $social['twitter_username'] ) ) {
			$twitter_tags['twitter:site'] = '@' . ltrim( (string) $social['twitter_username'], '@' );
		}
		$final_twitter_image = '' !== $twitter_image ? $twitter_image : $image;
		if ( '' !== $final_twitter_image ) {
			$twitter_tags['twitter:image'] = $final_twitter_image;
		}

		foreach ( $twitter_tags as $name => $value ) {
			if ( '' === $value ) {
				continue;
			}
			printf( "<meta name=\"%s\" content=\"%s\" />\n", esc_attr( $name ), esc_attr( $value ) );
		}
	}

	private function resolve_description( int $post_id ): string {
		if ( ! $post_id ) {
			return '';
		}

		$custom = (string) $this->repository->get( $post_id, 'description' );
		if ( '' !== $custom ) {
			return $custom;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$source = '' !== $post->post_excerpt ? $post->post_excerpt : $post->post_content;

		return wp_trim_words( wp_strip_all_tags( $source ), 30, '…' );
	}
}
