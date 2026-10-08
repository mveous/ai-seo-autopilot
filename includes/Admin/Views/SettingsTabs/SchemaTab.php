<?php
/**
 * "Schema" settings tab.
 *
 * @package AISEOAutopilot\Admin\Views\SettingsTabs
 */

namespace AISEOAutopilot\Admin\Views\SettingsTabs;

use AISEOAutopilot\Admin\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SchemaTab implements View {

	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct( private readonly array $values ) {}

	public function render(): void {
		$schema = $this->values['schema'];
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-seo-form">
			<input type="hidden" name="action" value="ai_seo_autopilot_save_settings" />
			<input type="hidden" name="tab" value="schema" />
			<?php wp_nonce_field( 'ai_seo_autopilot_settings' ); ?>

			<div class="ai-seo-field">
				<label for="organization_name"><?php esc_html_e( 'Organization name', 'ai-seo-autopilot' ); ?></label>
				<input type="text" name="settings[organization_name]" id="organization_name" value="<?php echo esc_attr( $schema['organization_name'] ); ?>" />
			</div>

			<div class="ai-seo-field">
				<label for="organization_type"><?php esc_html_e( 'Organization type', 'ai-seo-autopilot' ); ?></label>
				<select name="settings[organization_type]" id="organization_type">
					<?php foreach ( array( 'Organization', 'LocalBusiness', 'Corporation', 'EducationalOrganization', 'NGO' ) as $type ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $schema['organization_type'], $type ); ?>><?php echo esc_html( $type ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="ai-seo-field">
				<label for="organization_logo"><?php esc_html_e( 'Organization logo URL', 'ai-seo-autopilot' ); ?></label>
				<input type="url" name="settings[organization_logo]" id="organization_logo" value="<?php echo esc_attr( $schema['organization_logo'] ); ?>" placeholder="https://" />
			</div>

			<button type="submit" class="ai-seo-button ai-seo-button--primary"><?php esc_html_e( 'Save Changes', 'ai-seo-autopilot' ); ?></button>
		</form>
		<?php
	}
}
