<?php
/**
 * Appends the sitemap reference to WordPress's virtual robots.txt. Only
 * runs when no physical robots.txt file exists in the site root (that's
 * how the `robots_txt` filter itself works) and only when this plugin
 * is the one controlling the sitemap output.
 *
 * @package AISEOAutopilot\Sitemap
 */

namespace AISEOAutopilot\Sitemap;

use AISEOAutopilot\Compatibility\SeoPluginDetector;
use AISEOAutopilot\Core\Plugin;
use AISEOAutopilot\Core\Registrable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RobotsTxt implements Registrable {

	private ?SeoPluginDetector $detector = null;

	public function register(): void {
		/** @var SeoPluginDetector|null $detector */
		$this->detector = Plugin::instance()->get( 'compat.seo_plugins' );

		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 10, 2 );
	}

	public function filter_robots_txt( string $output, $public ): string {
		if ( null !== $this->detector && ! $this->detector->controls( 'sitemap' ) ) {
			return $output;
		}

		/** @var SitemapGenerator|null $sitemap */
		$sitemap = Plugin::instance()->get( 'sitemap.generator' );

		if ( ! $sitemap || ! $sitemap->is_enabled() ) {
			return $output;
		}

		if ( false !== strpos( $output, 'Sitemap:' ) ) {
			return $output;
		}

		return rtrim( $output ) . "\n\nSitemap: " . home_url( '/sitemap.xml' ) . "\n";
	}
}
