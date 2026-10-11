<?php
/**
 * Shared admin topbar/nav, rendered at the top of every top-level page.
 *
 * @package Aasa\Admin\Views
 */

namespace Aasa\Admin\Views;

use Aasa\Admin\Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HeaderPartial implements View {

	public function __construct( private readonly string $active_tab = 'dashboard' ) {}

	public function render(): void {
		$active_tab = $this->active_tab;

		$nav = array(
			'dashboard' => array( 'label' => __( 'Dashboard', 'ai-autopilot-seo-assistant' ), 'page' => Menu::DASHBOARD_SLUG ),
			'settings'  => array( 'label' => __( 'Settings', 'ai-autopilot-seo-assistant' ), 'page' => Menu::SETTINGS_SLUG ),
		);
		?>
		<div class="aasa-wrap">
			<div class="aasa-topbar">
				<div class="aasa-topbar__brand">
					<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
					<span class="aasa-topbar__title"><?php esc_html_e( 'AI Autopilot SEO Assistant', 'ai-autopilot-seo-assistant' ); ?></span>
				</div>
				<nav class="aasa-topbar__nav">
					<?php foreach ( $nav as $key => $item ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $item['page'] ) ); ?>"
							class="aasa-topbar__link <?php echo $active_tab === $key ? 'is-active' : ''; ?>">
							<?php echo esc_html( $item['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			</div>
		</div>
		<?php
	}
}
