<?php
/**
 * "General" settings tab.
 *
 * @package Aasa\Admin\Views\SettingsTabs
 */

namespace Aasa\Admin\Views\SettingsTabs;

use Aasa\Admin\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GeneralTab implements View {

	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct( private readonly array $values ) {}

	public function render(): void {
		$general = $this->values['general'];
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aasa-form">
			<input type="hidden" name="action" value="aasa_save_settings" />
			<input type="hidden" name="tab" value="general" />
			<?php wp_nonce_field( 'aasa_settings' ); ?>

			<div class="aasa-field">
				<label for="site_type"><?php esc_html_e( 'What kind of website is this?', 'ai-autopilot-seo-assistant' ); ?></label>
				<select name="settings[site_type]" id="site_type">
					<?php
					$options = array(
						'blog'      => __( 'Blog', 'ai-autopilot-seo-assistant' ),
						'business'  => __( 'Business website', 'ai-autopilot-seo-assistant' ),
						'ecommerce' => __( 'Online store', 'ai-autopilot-seo-assistant' ),
						'portfolio' => __( 'Portfolio', 'ai-autopilot-seo-assistant' ),
						'news'      => __( 'News / publisher', 'ai-autopilot-seo-assistant' ),
						'local'     => __( 'Local business', 'ai-autopilot-seo-assistant' ),
					);
					foreach ( $options as $value => $label ) :
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $general['site_type'], $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="aasa-field__help"><?php esc_html_e( 'Helps AI tailor recommendations and schema types to your site.', 'ai-autopilot-seo-assistant' ); ?></p>
			</div>

			<div class="aasa-field aasa-field--checkbox">
				<label>
					<input type="checkbox" name="settings[disable_seo_plugin_notices]" value="1" <?php checked( ! empty( $general['disable_seo_plugin_notices'] ) ); ?> />
					<?php esc_html_e( 'Hide notices about other active SEO plugins', 'ai-autopilot-seo-assistant' ); ?>
				</label>
			</div>

			<button type="submit" class="aasa-button aasa-button--primary"><?php esc_html_e( 'Save Changes', 'ai-autopilot-seo-assistant' ); ?></button>
		</form>
		<?php
	}
}
