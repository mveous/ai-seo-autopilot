<?php
/**
 * "General" settings tab.
 *
 * @package AISEOAutopilot\Admin\Views\SettingsTabs
 */

namespace AISEOAutopilot\Admin\Views\SettingsTabs;

use AISEOAutopilot\Admin\Views\View;

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
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-seo-form">
			<input type="hidden" name="action" value="ai_seo_autopilot_save_settings" />
			<input type="hidden" name="tab" value="general" />
			<?php wp_nonce_field( 'ai_seo_autopilot_settings' ); ?>

			<div class="ai-seo-field">
				<label for="site_type"><?php esc_html_e( 'What kind of website is this?', 'ai-seo-autopilot' ); ?></label>
				<select name="settings[site_type]" id="site_type">
					<?php
					$options = array(
						'blog'      => __( 'Blog', 'ai-seo-autopilot' ),
						'business'  => __( 'Business website', 'ai-seo-autopilot' ),
						'ecommerce' => __( 'Online store', 'ai-seo-autopilot' ),
						'portfolio' => __( 'Portfolio', 'ai-seo-autopilot' ),
						'news'      => __( 'News / publisher', 'ai-seo-autopilot' ),
						'local'     => __( 'Local business', 'ai-seo-autopilot' ),
					);
					foreach ( $options as $value => $label ) :
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $general['site_type'], $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="ai-seo-field__help"><?php esc_html_e( 'Helps AI tailor recommendations and schema types to your site.', 'ai-seo-autopilot' ); ?></p>
			</div>

			<div class="ai-seo-field ai-seo-field--checkbox">
				<label>
					<input type="checkbox" name="settings[disable_seo_plugin_notices]" value="1" <?php checked( ! empty( $general['disable_seo_plugin_notices'] ) ); ?> />
					<?php esc_html_e( 'Hide notices about other active SEO plugins', 'ai-seo-autopilot' ); ?>
				</label>
			</div>

			<button type="submit" class="ai-seo-button ai-seo-button--primary"><?php esc_html_e( 'Save Changes', 'ai-seo-autopilot' ); ?></button>
		</form>
		<?php
	}
}
