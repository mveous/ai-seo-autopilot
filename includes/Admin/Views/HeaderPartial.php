<?php
/**
 * Shared admin topbar/nav, rendered at the top of every top-level page.
 *
 * @package Scvx\Admin\Views
 */

namespace Scvx\Admin\Views;

use Scvx\Admin\Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HeaderPartial implements View {

	public function __construct( private readonly string $active_tab = 'dashboard' ) {}

	public function render(): void {
		$active_tab = $this->active_tab;

		$nav = array(
			'dashboard' => array( 'label' => __( 'Dashboard', 'scanvexa-seo' ), 'page' => Menu::DASHBOARD_SLUG ),
			'settings'  => array( 'label' => __( 'Settings', 'scanvexa-seo' ), 'page' => Menu::SETTINGS_SLUG ),
		);
		?>
		<div class="scvx-wrap">
			<div class="scvx-topbar">
				<div class="scvx-topbar__brand">
					<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
					<span class="scvx-topbar__title"><?php esc_html_e( 'Scanvexa SEO — AI Website Analyzer', 'scanvexa-seo' ); ?></span>
				</div>
				<nav class="scvx-topbar__nav">
					<?php foreach ( $nav as $key => $item ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $item['page'] ) ); ?>"
							class="scvx-topbar__link <?php echo $active_tab === $key ? 'is-active' : ''; ?>">
							<?php echo esc_html( $item['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			</div>
		</div>
		<?php
	}
}
