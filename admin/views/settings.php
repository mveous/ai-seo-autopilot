<?php
/**
 * Settings view: tabbed, one <form> per tab, posts to admin-post.php.
 *
 * @package AISEOAutopilot
 *
 * @var \AISEOAutopilot\Features\FeatureManager $features
 * @var \AISEOAutopilot\Admin\Settings          $settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is require()'d inside a method's local scope (see Menu::render_view()), not evaluated as a standalone global scope.

use AISEOAutopilot\Admin\Menu;
use AISEOAutopilot\Core\Plugin;

$active_tab = 'settings';
require __DIR__ . '/partials/header.php';

$plugin    = Plugin::instance();
$upgrade   = $plugin->get( 'upgrade' );
$providers = $plugin->get( 'ai.providers' );

$tabs = array(
	'general'   => array( 'label' => __( 'General', 'ai-seo-autopilot' ), 'pro' => false ),
	'ai'        => array( 'label' => __( 'AI Providers', 'ai-seo-autopilot' ), 'pro' => false ),
	'seo'       => array( 'label' => __( 'SEO', 'ai-seo-autopilot' ), 'pro' => false ),
	'content'   => array( 'label' => __( 'Content', 'ai-seo-autopilot' ), 'pro' => false ),
	'schema'    => array( 'label' => __( 'Schema', 'ai-seo-autopilot' ), 'pro' => false ),
	'sitemap'   => array( 'label' => __( 'Sitemap', 'ai-seo-autopilot' ), 'pro' => false ),
	'social'    => array( 'label' => __( 'Social', 'ai-seo-autopilot' ), 'pro' => false ),
	'compatibility' => array( 'label' => __( 'Compatibility', 'ai-seo-autopilot' ), 'pro' => false ),
	'internal_links' => array( 'label' => __( 'Internal Links', 'ai-seo-autopilot' ), 'pro' => true, 'feature' => 'internal_linking' ),
	'automation'      => array( 'label' => __( 'Automation', 'ai-seo-autopilot' ), 'pro' => true, 'feature' => 'seo_autopilot' ),
	'search_console'  => array( 'label' => __( 'Search Console', 'ai-seo-autopilot' ), 'pro' => true, 'feature' => 'search_console' ),
	'woocommerce'     => array( 'label' => __( 'WooCommerce', 'ai-seo-autopilot' ), 'pro' => true, 'feature' => 'woocommerce_seo' ),
	'local_seo'       => array( 'label' => __( 'Local SEO', 'ai-seo-autopilot' ), 'pro' => true, 'feature' => 'local_seo' ),
	'advanced'  => array( 'label' => __( 'Advanced', 'ai-seo-autopilot' ), 'pro' => false ),
);

$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! isset( $tabs[ $current ] ) ) {
	$current = 'general';
}

$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$values = $settings->get_all();
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
					<?php if ( ! empty( $tab['pro'] ) && ! $features->is_available( $tab['feature'] ) ) : ?>
						<span class="ai-seo-badge ai-seo-badge--pro ai-seo-badge--sm"><?php esc_html_e( 'PRO', 'ai-seo-autopilot' ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="ai-seo-settings-content">
			<?php
			if ( ! empty( $tabs[ $current ]['pro'] ) && ! $features->is_available( $tabs[ $current ]['feature'] ) ) {
				$feature = $features->get( $tabs[ $current ]['feature'] );
				if ( $feature ) {
					echo '<div class="ai-seo-grid ai-seo-grid--cards ai-seo-grid--single">';
					$upgrade->render_locked_feature_card( $tabs[ $current ]['feature'], $feature );
					echo '</div>';
				}
			} else {
				require __DIR__ . '/settings-tabs/' . $current . '.php';
			}
			?>
		</div>
	</div>
</div>
