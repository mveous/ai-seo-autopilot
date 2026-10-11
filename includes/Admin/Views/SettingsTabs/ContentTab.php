<?php
/**
 * "Content" settings tab.
 *
 * @package Aasa\Admin\Views\SettingsTabs
 */

namespace Aasa\Admin\Views\SettingsTabs;

use Aasa\Admin\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContentTab implements View {

	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct( private readonly array $values ) {}

	public function render(): void {
		$content = $this->values['content'];
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aasa-form">
			<input type="hidden" name="action" value="aasa_save_settings" />
			<input type="hidden" name="tab" value="content" />
			<?php wp_nonce_field( 'aasa_settings' ); ?>

			<div class="aasa-field aasa-field--checkbox">
				<label>
					<input type="checkbox" name="settings[focus_keyword_enabled]" value="1" <?php checked( ! empty( $content['focus_keyword_enabled'] ) ); ?> />
					<?php esc_html_e( 'Enable focus keyword field and keyword-based content analysis in the editor', 'ai-autopilot-seo-assistant' ); ?>
				</label>
			</div>

			<button type="submit" class="aasa-button aasa-button--primary"><?php esc_html_e( 'Save Changes', 'ai-autopilot-seo-assistant' ); ?></button>
		</form>
		<?php
	}
}
