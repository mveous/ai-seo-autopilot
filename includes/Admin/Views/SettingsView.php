<?php
/**
 * Settings page: tabbed, one <form> per tab, posts to admin-post.php.
 *
 * @package AISEOAutopilot\Admin\Views
 */

namespace AISEOAutopilot\Admin\Views;

use AISEOAutopilot\Admin\Menu;
use AISEOAutopilot\Admin\Settings;
use AISEOAutopilot\Admin\Views\SettingsTabs\AdvancedTab;
use AISEOAutopilot\Admin\Views\SettingsTabs\AiTab;
use AISEOAutopilot\Admin\Views\SettingsTabs\CompatibilityTab;
use AISEOAutopilot\Admin\Views\SettingsTabs\ContentTab;
use AISEOAutopilot\Admin\Views\SettingsTabs\GeneralTab;
use AISEOAutopilot\Admin\Views\SettingsTabs\SchemaTab;
use AISEOAutopilot\Admin\Views\SettingsTabs\SitemapTab;
use AISEOAutopilot\Admin\Views\SettingsTabs\SocialTab;
use AISEOAutopilot\Admin\Views\SettingsTabs\SeoTab;
use AISEOAutopilot\Core\Plugin;
use AISEOAutopilot\Features\FeatureManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsView implements View {

	public function __construct(
		private readonly FeatureManager $features,
		private readonly Settings $settings
	) {}

	public function render(): void {
		( new HeaderPartial( 'settings' ) )->render();

		$features = $this->features;
		$plugin   = Plugin::instance();

		$tabs = array(
			'general'   => array( 'label' => __( 'General', 'ai-seo-autopilot' ) ),
			'ai'        => array( 'label' => __( 'AI Providers', 'ai-seo-autopilot' ) ),
			'seo'       => array( 'label' => __( 'SEO', 'ai-seo-autopilot' ) ),
			'content'   => array( 'label' => __( 'Content', 'ai-seo-autopilot' ) ),
			'schema'    => array( 'label' => __( 'Schema', 'ai-seo-autopilot' ) ),
			'sitemap'   => array( 'label' => __( 'Sitemap', 'ai-seo-autopilot' ) ),
			'social'    => array( 'label' => __( 'Social', 'ai-seo-autopilot' ) ),
			'compatibility' => array( 'label' => __( 'Compatibility', 'ai-seo-autopilot' ) ),
			'advanced'  => array( 'label' => __( 'Advanced', 'ai-seo-autopilot' ) ),
		);

		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $current ] ) ) {
			$current = 'general';
		}

		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$values = $this->settings->get_all();
		?>
		<div class="ai-seo-page ai-seo-page--settings">

			<?php if ( 'saved' === $status ) : ?>
				<div class="ai-seo-notice ai-seo-notice--success"><?php esc_html_e( 'Settings saved.', 'ai-seo-autopilot' ); ?></div>
			<?php elseif ( 'deleted' === $status ) : ?>
				<div class="ai-seo-notice ai-seo-notice--success"><?php esc_html_e( 'API key removed.', 'ai-seo-autopilot' ); ?></div>
			<?php elseif ( 'error' === $status ) : ?>
				<div class="ai-seo-notice ai-seo-notice--error"><?php echo esc_html( $message ?: __( 'Something went wrong.', 'ai-seo-autopilot' ) ); ?></div>
			<?php endif; ?>

			<div class="ai-seo-settings-layout">
				<nav class="ai-seo-settings-nav">
					<?php foreach ( $tabs as $key => $tab ) : ?>
						<a class="ai-seo-settings-nav__link <?php echo $current === $key ? 'is-active' : ''; ?>"
							href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::SETTINGS_SLUG . '&tab=' . $key ) ); ?>">
							<?php echo esc_html( $tab['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="ai-seo-settings-content">
					<?php $this->render_tab( $current, $values, $plugin ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $values
	 */
	private function render_tab( string $tab, array $values, Plugin $plugin ): void {
		$tab_view = match ( $tab ) {
			'ai'            => new AiTab( $values, $plugin->get( 'ai.providers' ) ),
			'compatibility' => new CompatibilityTab( $values, $plugin->get( 'compat.seo_plugins' ) ),
			'seo'           => new SeoTab( $values ),
			'content'       => new ContentTab( $values ),
			'schema'        => new SchemaTab( $values ),
			'sitemap'       => new SitemapTab( $values ),
			'social'        => new SocialTab( $values ),
			'advanced'      => new AdvancedTab( $values ),
			default         => new GeneralTab( $values ),
		};

		$tab_view->render();
	}
}
