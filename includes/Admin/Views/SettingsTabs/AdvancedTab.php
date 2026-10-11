<?php
/**
 * "Advanced" settings tab.
 *
 * @package Aasa\Admin\Views\SettingsTabs
 */

namespace Aasa\Admin\Views\SettingsTabs;

use Aasa\Admin\Views\View;

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
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aasa-form">
			<input type="hidden" name="action" value="aasa_save_settings" />
			<input type="hidden" name="tab" value="advanced" />
			<?php wp_nonce_field( 'aasa_settings' ); ?>

			<div class="aasa-field aasa-field--checkbox">
				<label>
					<input type="checkbox" name="settings[remove_data_on_uninstall]" value="1" <?php checked( ! empty( $advanced['remove_data_on_uninstall'] ) ); ?> />
					<?php esc_html_e( 'Remove all plugin data when the plugin is deleted', 'ai-autopilot-seo-assistant' ); ?>
				</label>
				<p class="aasa-field__help"><?php esc_html_e( 'Deletes all scans, issues, activity history, redirects, AI usage records and SEO metadata. This cannot be undone.', 'ai-autopilot-seo-assistant' ); ?></p>
			</div>

			<button type="submit" class="aasa-button aasa-button--primary"><?php esc_html_e( 'Save Changes', 'ai-autopilot-seo-assistant' ); ?></button>
		</form>
		<?php
	}
}
