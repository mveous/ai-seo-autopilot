<?php
/**
 * "Content" settings tab.
 *
 * @package Scvx\Admin\Views\SettingsTabs
 */

namespace Scvx\Admin\Views\SettingsTabs;

use Scvx\Admin\Views\View;

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
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scvx-form">
			<input type="hidden" name="action" value="scvx_save_settings" />
			<input type="hidden" name="tab" value="content" />
			<?php wp_nonce_field( 'scvx_settings' ); ?>

			<div class="scvx-field scvx-field--checkbox">
				<label>
					<input type="checkbox" name="settings[focus_keyword_enabled]" value="1" <?php checked( ! empty( $content['focus_keyword_enabled'] ) ); ?> />
					<?php esc_html_e( 'Enable focus keyword field and keyword-based content analysis in the editor', 'scanvexa-seo' ); ?>
				</label>
			</div>

			<button type="submit" class="scvx-button scvx-button--primary"><?php esc_html_e( 'Save Changes', 'scanvexa-seo' ); ?></button>
		</form>
		<?php
	}
}
