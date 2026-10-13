<?php
/**
 * Settings page: tabbed, one <form> per tab, posts to admin-post.php.
 *
 * @package Scvx\Admin\Views
 */

namespace Scvx\Admin\Views;

use Scvx\Admin\Menu;
use Scvx\Admin\Settings;
use Scvx\Admin\Views\SettingsTabs\AdvancedTab;
use Scvx\Admin\Views\SettingsTabs\AiTab;
use Scvx\Admin\Views\SettingsTabs\CompatibilityTab;
use Scvx\Admin\Views\SettingsTabs\ContentTab;
use Scvx\Admin\Views\SettingsTabs\GeneralTab;
use Scvx\Admin\Views\SettingsTabs\SchemaTab;
use Scvx\Admin\Views\SettingsTabs\SitemapTab;
use Scvx\Admin\Views\SettingsTabs\SocialTab;
use Scvx\Admin\Views\SettingsTabs\SeoTab;
use Scvx\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsView implements View {

	public function __construct(
		private readonly Settings $settings
	) {}

	public function render(): void {
		( new HeaderPartial( 'settings' ) )->render();

		$plugin   = Plugin::instance();

		$tabs = array(
			'general'   => array( 'label' => __( 'General', 'scanvexa-seo' ) ),
			'ai'        => array( 'label' => __( 'AI Providers', 'scanvexa-seo' ) ),
			'seo'       => array( 'label' => __( 'SEO', 'scanvexa-seo' ) ),
			'content'   => array( 'label' => __( 'Content', 'scanvexa-seo' ) ),
			'schema'    => array( 'label' => __( 'Schema', 'scanvexa-seo' ) ),
			'sitemap'   => array( 'label' => __( 'Sitemap', 'scanvexa-seo' ) ),
			'social'    => array( 'label' => __( 'Social', 'scanvexa-seo' ) ),
			'compatibility' => array( 'label' => __( 'Compatibility', 'scanvexa-seo' ) ),
			'advanced'  => array( 'label' => __( 'Advanced', 'scanvexa-seo' ) ),
		);

		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $current ] ) ) {
			$current = 'general';
		}

		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$values = $this->settings->get_all();
		?>
		<div class="scvx-page scvx-page--settings">

			<?php if ( 'saved' === $status ) : ?>
				<?php $this->render_toast( __( 'Settings saved.', 'scanvexa-seo' ), 'success' ); ?>
			<?php elseif ( 'deleted' === $status ) : ?>
				<?php $this->render_toast( __( 'API key removed.', 'scanvexa-seo' ), 'success' ); ?>
			<?php elseif ( 'error' === $status ) : ?>
				<?php $this->render_toast( $message ?: __( 'Something went wrong.', 'scanvexa-seo' ), 'error' ); ?>
			<?php endif; ?>

			<div class="scvx-settings-layout">
				<nav class="scvx-settings-nav">
					<?php foreach ( $tabs as $key => $tab ) : ?>
						<a class="scvx-settings-nav__link <?php echo $current === $key ? 'is-active' : ''; ?>"
							href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::SETTINGS_SLUG . '&tab=' . $key ) ); ?>">
							<?php echo esc_html( $tab['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="scvx-settings-content">
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
		<div id="scvx-toast" class="scvx-toast scvx-toast--<?php echo esc_attr( $type ); ?>" role="status">
			<span class="scvx-toast__message"><?php echo esc_html( $message ); ?></span>
			<button type="button" class="scvx-toast__close" aria-label="<?php esc_attr_e( 'Dismiss', 'scanvexa-seo' ); ?>">&times;</button>
		</div>
		<?php
		// Styling and dismiss behavior live in admin/css/admin.css and
		// admin/js/admin.js (enqueued by Admin\Assets) — never inline here.
	}
}
