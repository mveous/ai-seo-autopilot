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
				<?php $this->render_toast( __( 'Settings saved.', 'ai-seo-autopilot' ), 'success' ); ?>
			<?php elseif ( 'deleted' === $status ) : ?>
				<?php $this->render_toast( __( 'API key removed.', 'ai-seo-autopilot' ), 'success' ); ?>
			<?php elseif ( 'error' === $status ) : ?>
				<?php $this->render_toast( $message ?: __( 'Something went wrong.', 'ai-seo-autopilot' ), 'error' ); ?>
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

	/**
	 * A transient toast notice — auto-dismisses after 10 seconds or on
	 * manual close, and strips status/message from the URL immediately so
	 * refreshing the page never re-shows a past submission's notice. The
	 * $status/$message query args exist only to survive the redirect from
	 * the admin-post handler; they are not meant to be a permanent part of
	 * this page's URL.
	 */
	private function render_toast( string $message, string $type ): void {
		?>
		<div id="ai-seo-toast" class="ai-seo-toast ai-seo-toast--<?php echo esc_attr( $type ); ?>" role="status">
			<span class="ai-seo-toast__message"><?php echo esc_html( $message ); ?></span>
			<button type="button" class="ai-seo-toast__close" aria-label="<?php esc_attr_e( 'Dismiss', 'ai-seo-autopilot' ); ?>">&times;</button>
		</div>
		<?php
		// Styling and dismiss behavior live in admin/css/admin.css and
		// admin/js/admin.js (enqueued by Admin\Assets) — never inline here.
	}
}
