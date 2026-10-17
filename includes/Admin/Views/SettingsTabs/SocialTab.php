<?php
/**
 * "Social" settings tab.
 *
 * @package Scvx\Admin\Views\SettingsTabs
 */

namespace Scvx\Admin\Views\SettingsTabs;

use Scvx\Admin\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SocialTab implements View {

	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct( private array $values ) {}

	public function render(): void {
		$social = $this->values['social'];
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scvx-form">
			<input type="hidden" name="action" value="scvx_save_settings" />
			<input type="hidden" name="tab" value="social" />
			<?php wp_nonce_field( 'scvx_settings' ); ?>

			<div class="scvx-field">
				<label for="default_og_image"><?php esc_html_e( 'Default social share image URL', 'scanvexa-seo' ); ?></label>
				<input type="url" name="settings[default_og_image]" id="default_og_image" value="<?php echo esc_attr( $social['default_og_image'] ); ?>" placeholder="https://" />
				<p class="scvx-field__help"><?php esc_html_e( 'Used when a page has no featured image.', 'scanvexa-seo' ); ?></p>
			</div>

			<div class="scvx-field">
				<label for="facebook_app_id"><?php esc_html_e( 'Facebook App ID', 'scanvexa-seo' ); ?></label>
				<input type="text" name="settings[facebook_app_id]" id="facebook_app_id" value="<?php echo esc_attr( $social['facebook_app_id'] ); ?>" />
			</div>

			<div class="scvx-field">
				<label for="twitter_username"><?php esc_html_e( 'X (Twitter) username', 'scanvexa-seo' ); ?></label>
				<input type="text" name="settings[twitter_username]" id="twitter_username" value="<?php echo esc_attr( $social['twitter_username'] ); ?>" placeholder="yourhandle" />
			</div>

			<button type="submit" class="scvx-button scvx-button--primary"><?php esc_html_e( 'Save Changes', 'scanvexa-seo' ); ?></button>
		</form>
		<?php
	}
}
