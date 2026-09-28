<?php
/**
 * "Compatibility" settings tab.
 *
 * @package AISEOAutopilot\Admin\Views\SettingsTabs
 */

namespace AISEOAutopilot\Admin\Views\SettingsTabs;

use AISEOAutopilot\Admin\Views\View;
use AISEOAutopilot\Compatibility\SeoPluginDetector;

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
			'titles'    => __( 'SEO Titles', 'ai-seo-autopilot' ),
			'meta'      => __( 'Meta Descriptions', 'ai-seo-autopilot' ),
			'canonical' => __( 'Canonical URLs', 'ai-seo-autopilot' ),
			'opengraph' => __( 'Open Graph / Twitter Cards', 'ai-seo-autopilot' ),
			'schema'    => __( 'Schema (JSON-LD)', 'ai-seo-autopilot' ),
			'sitemap'   => __( 'XML Sitemap', 'ai-seo-autopilot' ),
		);
		?>
		<?php if ( ! empty( $conflicts ) ) : ?>
			<div class="ai-seo-notice ai-seo-notice--error" style="margin:0 0 18px;">
				<?php
				printf(
					/* translators: %s: comma-separated list of other active SEO plugins */
					esc_html__( 'Active SEO plugin(s) detected: %s. Choose below which plugin should control each output area to avoid duplicates.', 'ai-seo-autopilot' ),
					esc_html( implode( ', ', $conflicts ) )
				);
				?>
			</div>
		<?php else : ?>
			<p class="ai-seo-field__help" style="margin-top:0;"><?php esc_html_e( 'No other active SEO plugin detected. AI SEO Autopilot controls all areas below by default.', 'ai-seo-autopilot' ); ?></p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-seo-form">
			<input type="hidden" name="action" value="ai_seo_autopilot_save_settings" />
			<input type="hidden" name="tab" value="compatibility" />
			<?php wp_nonce_field( 'ai_seo_autopilot_settings' ); ?>

			<?php foreach ( $areas as $area => $label ) : ?>
				<div class="ai-seo-field ai-seo-field--checkbox">
					<label>
						<input type="checkbox" name="settings[control_<?php echo esc_attr( $area ); ?>]" value="1" <?php checked( ! empty( $compat[ 'control_' . $area ] ) ); ?> />
						<?php
						printf(
							/* translators: %s: output area label, e.g. "SEO Titles" */
							esc_html__( 'AI SEO Autopilot controls %s', 'ai-seo-autopilot' ),
							esc_html( $label )
						);
						?>
					</label>
					<p class="ai-seo-field__help"><?php esc_html_e( 'Uncheck to let another active SEO plugin output this instead.', 'ai-seo-autopilot' ); ?></p>
				</div>
			<?php endforeach; ?>

			<button type="submit" class="ai-seo-button ai-seo-button--primary"><?php esc_html_e( 'Save Changes', 'ai-seo-autopilot' ); ?></button>
		</form>
		<?php
	}
}
