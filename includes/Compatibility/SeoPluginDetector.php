<?php
/**
 * Detects other active SEO plugins and exposes, per output area, whether
 * *this* plugin should render its own output — so we never duplicate
 * title tags, meta descriptions, sitemaps, schema, canonicals or Open
 * Graph tags alongside Yoast/Rank Math/AIOSEO/SEOPress.
 *
 * @package AISEOAutopilot\Compatibility
 */

namespace AISEOAutopilot\Compatibility;

use AISEOAutopilot\Admin\Settings;
use AISEOAutopilot\Core\Registrable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SeoPluginDetector implements Registrable {

	public const AREAS = array( 'titles', 'meta', 'canonical', 'opengraph', 'schema', 'sitemap' );

	/**
	 * Detection signatures: label => [class_exists checks...].
	 *
	 * @var array<string,string[]>
	 */
	private const SIGNATURES = array(
		'Yoast SEO'      => array( 'WPSEO_Options', 'Yoast\\WP\\SEO\\Main' ),
		'Rank Math'      => array( 'RankMath' ),
		'All in One SEO' => array( 'AIOSEO\\Plugin\\AIOSEO', 'AIOSEOP_Core' ),
		'SEOPress'       => array( 'SEOPress\\Core' ),
	);

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
	}

	/**
	 * @return array<string,string> label => label (kept as a map for easy iteration/lookup)
	 */
	public function get_active_conflicts(): array {
		$found = array();

		foreach ( self::SIGNATURES as $label => $classes ) {
			foreach ( $classes as $class ) {
				if ( class_exists( $class ) ) {
					$found[ $label ] = $label;
					break;
				}
			}
		}

		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'seopress/seopress.php' ) ) {
			$found['SEOPress'] = 'SEOPress';
		}

		return $found;
	}

	public function has_conflict(): bool {
		return ! empty( $this->get_active_conflicts() );
	}

	/**
	 * Whether this plugin should render output for a given area.
	 * Defaults to true (this plugin controls it) unless the site owner
	 * has explicitly handed the area to another plugin in Settings →
	 * Compatibility.
	 */
	public function controls( string $area ): bool {
		if ( ! in_array( $area, self::AREAS, true ) ) {
			return true;
		}

		$settings = get_option( Settings::OPTION, array() );

		return (bool) ( $settings['compatibility'][ 'control_' . $area ] ?? true );
	}

	public function maybe_render_notice(): void {
		if ( ! $this->has_conflict() ) {
			return;
		}

		$settings = get_option( Settings::OPTION, array() );
		if ( ! empty( $settings['general']['disable_seo_plugin_notices'] ) ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false === strpos( (string) $screen->id, 'ai-seo-autopilot' ) && 'plugins' !== $screen->id ) {
			return;
		}

		$others = implode( ', ', $this->get_active_conflicts() );
		$url    = admin_url( 'admin.php?page=ai-seo-autopilot-settings&tab=compatibility' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %s: comma-separated list of other active SEO plugins */
					esc_html__( 'AI SEO Autopilot detected another active SEO plugin (%s). To avoid duplicate titles, meta tags, or sitemaps, choose which plugin controls each area.', 'ai-seo-autopilot' ),
					esc_html( $others )
				);
				?>
				<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Review compatibility settings', 'ai-seo-autopilot' ); ?></a>
			</p>
		</div>
		<?php
	}
}
