<?php
/**
 * Shared admin topbar/nav, rendered at the top of every top-level page.
 *
 * @package AISEOAutopilot\Admin\Views
 */

namespace AISEOAutopilot\Admin\Views;

use AISEOAutopilot\Admin\Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HeaderPartial implements View {

	public function __construct( private readonly string $active_tab = 'dashboard' ) {}

	public function render(): void {
		$active_tab = $this->active_tab;

		$nav = array(
			'dashboard' => array( 'label' => __( 'Dashboard', 'ai-seo-autopilot' ), 'page' => Menu::DASHBOARD_SLUG ),
			'settings'  => array( 'label' => __( 'Settings', 'ai-seo-autopilot' ), 'page' => Menu::SETTINGS_SLUG ),
		);
		?>
		<div class="ai-seo-wrap">
			<div class="ai-seo-topbar">
				<div class="ai-seo-topbar__brand">
					<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
					<span class="ai-seo-topbar__title"><?php esc_html_e( 'AI SEO Autopilot', 'ai-seo-autopilot' ); ?></span>
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
		<?php
	}
}
