<?php
/**
 * "Advanced" settings tab.
 *
 * @package Scvx\Admin\Views\SettingsTabs
 */

namespace Scvx\Admin\Views\SettingsTabs;

use Scvx\Admin\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdvancedTab implements View {

	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct( private readonly array $values ) {}

	public function render(): void {
		$advanced = $this->values['advanced'];
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scvx-form">
			<input type="hidden" name="action" value="scvx_save_settings" />
			<input type="hidden" name="tab" value="advanced" />
			<?php wp_nonce_field( 'scvx_settings' ); ?>

			<div class="scvx-field scvx-field--checkbox">
				<label>
					<input type="checkbox" name="settings[remove_data_on_uninstall]" value="1" <?php checked( ! empty( $advanced['remove_data_on_uninstall'] ) ); ?> />
					<?php esc_html_e( 'Remove all plugin data when the plugin is deleted', 'scanvexa-seo' ); ?>
				</label>
				<p class="scvx-field__help"><?php esc_html_e( 'Deletes all scans, issues, activity history, redirects, AI usage records and SEO metadata. This cannot be undone.', 'scanvexa-seo' ); ?></p>
			</div>

			<button type="submit" class="scvx-button scvx-button--primary"><?php esc_html_e( 'Save Changes', 'scanvexa-seo' ); ?></button>
		</form>
		<?php
	}
}
