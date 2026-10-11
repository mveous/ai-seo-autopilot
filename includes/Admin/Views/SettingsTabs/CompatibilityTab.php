<?php
/**
 * "Compatibility" settings tab.
 *
 * @package Aasa\Admin\Views\SettingsTabs
 */

namespace Aasa\Admin\Views\SettingsTabs;

use Aasa\Admin\Views\View;
use Aasa\Compatibility\SeoPluginDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CompatibilityTab implements View {

	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct(
		private readonly array $values,
		private readonly ?SeoPluginDetector $detector
	) {}

	public function render(): void {
		$conflicts = $this->detector ? $this->detector->get_active_conflicts() : array();
		$compat    = $this->values['compatibility'];

		$areas = array(
			'titles'    => __( 'SEO Titles', 'ai-autopilot-seo-assistant' ),
			'meta'      => __( 'Meta Descriptions', 'ai-autopilot-seo-assistant' ),
			'canonical' => __( 'Canonical URLs', 'ai-autopilot-seo-assistant' ),
			'opengraph' => __( 'Open Graph / Twitter Cards', 'ai-autopilot-seo-assistant' ),
			'schema'    => __( 'Schema (JSON-LD)', 'ai-autopilot-seo-assistant' ),
			'sitemap'   => __( 'XML Sitemap', 'ai-autopilot-seo-assistant' ),
		);
		?>
		<?php if ( ! empty( $conflicts ) ) : ?>
			<div class="aasa-notice aasa-notice--error" style="margin:0 0 18px;">
				<?php
				printf(
					/* translators: %s: comma-separated list of other active SEO plugins */
					esc_html__( 'Active SEO plugin(s) detected: %s. Choose below which plugin should control each output area to avoid duplicates.', 'ai-autopilot-seo-assistant' ),
					esc_html( implode( ', ', $conflicts ) )
				);
				?>
			</div>
		<?php else : ?>
			<p class="aasa-field__help" style="margin-top:0;"><?php esc_html_e( 'No other active SEO plugin detected. AI Autopilot SEO Assistant controls all areas below by default.', 'ai-autopilot-seo-assistant' ); ?></p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aasa-form">
			<input type="hidden" name="action" value="aasa_save_settings" />
			<input type="hidden" name="tab" value="compatibility" />
			<?php wp_nonce_field( 'aasa_settings' ); ?>

			<?php foreach ( $areas as $area => $label ) : ?>
				<div class="aasa-field aasa-field--checkbox">
					<label>
						<input type="checkbox" name="settings[control_<?php echo esc_attr( $area ); ?>]" value="1" <?php checked( ! empty( $compat[ 'control_' . $area ] ) ); ?> />
						<?php
						printf(
							/* translators: %s: output area label, e.g. "SEO Titles" */
							esc_html__( 'AI Autopilot SEO Assistant controls %s', 'ai-autopilot-seo-assistant' ),
							esc_html( $label )
						);
						?>
					</label>
					<p class="aasa-field__help"><?php esc_html_e( 'Uncheck to let another active SEO plugin output this instead.', 'ai-autopilot-seo-assistant' ); ?></p>
				</div>
			<?php endforeach; ?>

			<button type="submit" class="aasa-button aasa-button--primary"><?php esc_html_e( 'Save Changes', 'ai-autopilot-seo-assistant' ); ?></button>
		</form>
		<?php
	}
}
