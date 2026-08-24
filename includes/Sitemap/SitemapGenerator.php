<?php
/**
 * Lightweight XML sitemap: /sitemap.xml (index) + /sitemap-{post_type}.xml.
 * Rendered on-demand via a rewrite rule + template_redirect, with output
 * cached in a transient so repeat crawler requests don't rebuild the
 * query on every hit.
 *
 * @package AISEOAutopilot\Sitemap
 */

namespace AISEOAutopilot\Sitemap;

use AISEOAutopilot\Admin\Settings;
use AISEOAutopilot\Compatibility\SeoPluginDetector;
use AISEOAutopilot\Core\Plugin;
use AISEOAutopilot\Core\Registrable;
use AISEOAutopilot\SEO\Metadata\MetaRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SitemapGenerator implements Registrable {

	private const QUERY_VAR   = 'ai_seo_sitemap';
	private const MAX_PER_MAP = 2000;
	private const CACHE_TTL   = HOUR_IN_SECONDS;

	private ?SeoPluginDetector $detector = null;

	public function register(): void {
		/** @var SeoPluginDetector|null $detector */
		$this->detector = Plugin::instance()->get( 'compat.seo_plugins' );

		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite' ), 20 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_sitemap' ) );

		add_action( 'save_post', array( $this, 'purge_cache' ) );
		add_action( 'deleted_post', array( $this, 'purge_cache' ) );
	}

	public function add_rewrite_rules(): void {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?' . self::QUERY_VAR . '=index', 'top' );
		add_rewrite_rule( '^sitemap-([^/]+)\.xml$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	public function maybe_flush_rewrite(): void {
		if ( get_option( 'ai_seo_autopilot_flush_rewrite' ) ) {
			flush_rewrite_rules( false );
			delete_option( 'ai_seo_autopilot_flush_rewrite' );
		}
	}

	/**
	 * @param string[] $vars
	 *
	 * @return string[]
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public function is_enabled(): bool {
		$settings = get_option( Settings::OPTION, array() );

		if ( empty( $settings['sitemap']['enabled'] ) ) {
			return false;
		}

		return null === $this->detector || $this->detector->controls( 'sitemap' );
	}

	public function maybe_render_sitemap(): void {
		$target = get_query_var( self::QUERY_VAR );

		if ( '' === $target || false === $target ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		$cache_key = 'ai_seo_sitemap_' . sanitize_key( $target );
		$xml       = get_transient( $cache_key );

		if ( false === $xml ) {
			$xml = 'index' === $target ? $this->build_index() : $this->build_urlset( sanitize_key( $target ) );

			if ( null === $xml ) {
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
				return;
			}

			set_transient( $cache_key, $xml, self::CACHE_TTL );
		}

		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built, escaped XML.
		exit;
	}

	private function build_index(): string {
		$post_types = $this->indexable_post_types();

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $post_types as $post_type ) {
			$last_post = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'fields'         => 'ids',
				)
			);

			if ( empty( $last_post ) ) {
				continue;
			}

			$xml .= "\t<sitemap>\n";
			$xml .= "\t\t<loc>" . esc_url( home_url( "/sitemap-{$post_type}.xml" ) ) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . esc_html( get_post_modified_time( 'c', true, $last_post[0] ) ) . "</lastmod>\n";
			$xml .= "\t</sitemap>\n";
		}

		$xml .= '</sitemapindex>';

		return $xml;
	}

	private function build_urlset( string $post_type ): ?string {
		if ( ! in_array( $post_type, $this->indexable_post_types(), true ) ) {
			return null;
		}

		$repository = new MetaRepository();

		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => self::MAX_PER_MAP,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => $repository->meta_key( 'robots_noindex' ),
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $query->posts as $post ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url( get_permalink( $post ) ) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . esc_html( get_post_modified_time( 'c', true, $post ) ) . "</lastmod>\n";
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		return $xml;
	}

	/**
	 * @return string[]
	 */
	private function indexable_post_types(): array {
		$settings = get_option( Settings::OPTION, array() );
		$excluded = (array) ( $settings['sitemap']['exclude_post_types'] ?? array() );

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );

		$post_types = array_diff( $post_types, $excluded );

		return array_values( $post_types );
	}

	public function purge_cache(): void {
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk-deleting transients by LIKE pattern; no core API exists for pattern-based transient deletion.
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_ai_seo_sitemap_' ) . '%'
			)
		);
	}
}
