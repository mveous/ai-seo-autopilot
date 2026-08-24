<?php
/**
 * Shared admin header/nav. Expects $active_tab (string) in scope.
 *
 * @package AISEOAutopilot
 *
 * @var string $active_tab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is require()'d inside a method's local scope (see Menu::render_view()), not evaluated as a standalone global scope.

use AISEOAutopilot\Admin\Menu;

$active_tab = $active_tab ?? 'dashboard';

$nav = array(
	'dashboard' => array( 'label' => __( 'Dashboard', 'ai-seo-autopilot' ), 'page' => Menu::DASHBOARD_SLUG ),
	'settings'  => array( 'label' => __( 'Settings', 'ai-seo-autopilot' ), 'page' => Menu::SETTINGS_SLUG ),
	'upgrade'   => array( 'label' => __( 'Upgrade to Pro', 'ai-seo-autopilot' ), 'page' => 'ai-seo-autopilot-upgrade' ),
);
?>
<div class="ai-seo-wrap">
	<div class="ai-seo-topbar">
		<div class="ai-seo-topbar__brand">
			<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
			<span class="ai-seo-topbar__title"><?php esc_html_e( 'AI SEO Autopilot', 'ai-seo-autopilot' ); ?></span>
			<?php $plan_badge = apply_filters( 'ai_seo_autopilot/plan_badge', __( 'FREE', 'ai-seo-autopilot' ) ); ?>
			<span class="ai-seo-badge ai-seo-badge--free"><?php echo esc_html( $plan_badge ); ?></span>
		</div>
		<nav class="ai-seo-topbar__nav">
			<?php foreach ( $nav as $key => $item ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $item['page'] ) ); ?>"
					class="ai-seo-topbar__link <?php echo $active_tab === $key ? 'is-active' : ''; ?>">
					<?php echo esc_html( $item['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	</div>
</div>
