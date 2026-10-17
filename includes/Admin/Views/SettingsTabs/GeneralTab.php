<?php
/**
 * "General" settings tab.
 *
 * @package Scvx\Admin\Views\SettingsTabs
 */

namespace Scvx\Admin\Views\SettingsTabs;

use Scvx\Admin\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GeneralTab implements View {

	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct( private array $values ) {}

	public function render(): void {
		$general = $this->values['general'];
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scvx-form">
			<input type="hidden" name="action" value="scvx_save_settings" />
			<input type="hidden" name="tab" value="general" />
			<?php wp_nonce_field( 'scvx_settings' ); ?>

			<div class="scvx-field">
				<label for="site_type"><?php esc_html_e( 'What kind of website is this?', 'scanvexa-seo' ); ?></label>
				<select name="settings[site_type]" id="site_type">
					<?php
					$options = array(
						'blog'      => __( 'Blog', 'scanvexa-seo' ),
						'business'  => __( 'Business website', 'scanvexa-seo' ),
						'ecommerce' => __( 'Online store', 'scanvexa-seo' ),
						'portfolio' => __( 'Portfolio', 'scanvexa-seo' ),
						'news'      => __( 'News / publisher', 'scanvexa-seo' ),
						'local'     => __( 'Local business', 'scanvexa-seo' ),
					);
					foreach ( $options as $value => $label ) :
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $general['site_type'], $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="scvx-field__help"><?php esc_html_e( 'Helps AI tailor recommendations and schema types to your site.', 'scanvexa-seo' ); ?></p>
			</div>

			<div class="scvx-field scvx-field--checkbox">
				<label>
					<input type="checkbox" name="settings[disable_seo_plugin_notices]" value="1" <?php checked( ! empty( $general['disable_seo_plugin_notices'] ) ); ?> />
					<?php esc_html_e( 'Hide notices about other active SEO plugins', 'scanvexa-seo' ); ?>
				</label>
			</div>

			<button type="submit" class="scvx-button scvx-button--primary"><?php esc_html_e( 'Save Changes', 'scanvexa-seo' ); ?></button>
		</form>
		<?php
	}
}
