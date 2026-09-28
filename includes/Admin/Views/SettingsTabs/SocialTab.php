<?php
/**
 * "Social" settings tab.
 *
 * @package AISEOAutopilot\Admin\Views\SettingsTabs
 */

namespace AISEOAutopilot\Admin\Views\SettingsTabs;

use AISEOAutopilot\Admin\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SocialTab implements View {

	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct( private readonly array $values ) {}

	public function render(): void {
		$social = $this->values['social'];
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-seo-form">
			<input type="hidden" name="action" value="ai_seo_autopilot_save_settings" />
			<input type="hidden" name="tab" value="social" />
			<?php wp_nonce_field( 'ai_seo_autopilot_settings' ); ?>

			<div class="ai-seo-field">
				<label for="default_og_image"><?php esc_html_e( 'Default social share image URL', 'ai-seo-autopilot' ); ?></label>
				<input type="url" name="settings[default_og_image]" id="default_og_image" value="<?php echo esc_attr( $social['default_og_image'] ); ?>" placeholder="https://" />
				<p class="ai-seo-field__help"><?php esc_html_e( 'Used when a page has no featured image.', 'ai-seo-autopilot' ); ?></p>
			</div>

			<div class="ai-seo-field">
				<label for="facebook_app_id"><?php esc_html_e( 'Facebook App ID', 'ai-seo-autopilot' ); ?></label>
				<input type="text" name="settings[facebook_app_id]" id="facebook_app_id" value="<?php echo esc_attr( $social['facebook_app_id'] ); ?>" />
			</div>

			<div class="ai-seo-field">
				<label for="twitter_username"><?php esc_html_e( 'X (Twitter) username', 'ai-seo-autopilot' ); ?></label>
				<input type="text" name="settings[twitter_username]" id="twitter_username" value="<?php echo esc_attr( $social['twitter_username'] ); ?>" placeholder="yourhandle" />
			</div>

			<button type="submit" class="ai-seo-button ai-seo-button--primary"><?php esc_html_e( 'Save Changes', 'ai-seo-autopilot' ); ?></button>
		</form>
		<?php
	}
}
