<?php
/**
 * "SEO" settings tab.
 *
 * @package Scvx\Admin\Views\SettingsTabs
 */

namespace Scvx\Admin\Views\SettingsTabs;

use Scvx\Admin\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SeoTab implements View {

	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct( private array $values ) {}

	public function render(): void {
		$seo = $this->values['seo'];
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scvx-form">
			<input type="hidden" name="action" value="scvx_save_settings" />
			<input type="hidden" name="tab" value="seo" />
			<?php wp_nonce_field( 'scvx_settings' ); ?>

			<div class="scvx-field">
				<label for="title_separator"><?php esc_html_e( 'Title separator', 'scanvexa-seo' ); ?></label>
				<select name="settings[title_separator]" id="title_separator">
					<?php foreach ( array( '-', '|', '·', '•', '—' ) as $sep ) : ?>
						<option value="<?php echo esc_attr( $sep ); ?>" <?php selected( $seo['title_separator'], $sep ); ?>><?php echo esc_html( $sep ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="scvx-field scvx-field--checkbox">
				<label>
					<input type="checkbox" name="settings[noindex_paginated]" value="1" <?php checked( ! empty( $seo['noindex_paginated'] ) ); ?> />
					<?php esc_html_e( 'Noindex paginated archives (page 2, 3, …)', 'scanvexa-seo' ); ?>
				</label>
			</div>

			<div class="scvx-field scvx-field--checkbox">
				<label>
					<input type="checkbox" name="settings[noindex_archives]" value="1" <?php checked( ! empty( $seo['noindex_archives'] ) ); ?> />
					<?php esc_html_e( 'Noindex date and author archives', 'scanvexa-seo' ); ?>
				</label>
			</div>

			<div class="scvx-field scvx-field--checkbox">
				<label>
					<input type="checkbox" name="settings[strip_category_base]" value="1" <?php checked( ! empty( $seo['strip_category_base'] ) ); ?> />
					<?php esc_html_e( 'Remove /category/ base from category URLs', 'scanvexa-seo' ); ?>
				</label>
			</div>

			<button type="submit" class="scvx-button scvx-button--primary"><?php esc_html_e( 'Save Changes', 'scanvexa-seo' ); ?></button>
		</form>
		<?php
	}
}
