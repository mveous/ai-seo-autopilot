<?php
/**
 * "Compatibility" settings tab.
 *
 * @package Scvx\Admin\Views\SettingsTabs
 */

namespace Scvx\Admin\Views\SettingsTabs;

use Scvx\Admin\Views\View;
use Scvx\Compatibility\SeoPluginDetector;

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
			'titles'    => __( 'SEO Titles', 'scanvexa-seo' ),
			'meta'      => __( 'Meta Descriptions', 'scanvexa-seo' ),
			'canonical' => __( 'Canonical URLs', 'scanvexa-seo' ),
			'opengraph' => __( 'Open Graph / Twitter Cards', 'scanvexa-seo' ),
			'schema'    => __( 'Schema (JSON-LD)', 'scanvexa-seo' ),
			'sitemap'   => __( 'XML Sitemap', 'scanvexa-seo' ),
		);
		?>
		<?php if ( ! empty( $conflicts ) ) : ?>
			<div class="scvx-notice scvx-notice--error" style="margin:0 0 18px;">
				<?php
				printf(
					/* translators: %s: comma-separated list of other active SEO plugins */
					esc_html__( 'Active SEO plugin(s) detected: %s. Choose below which plugin should control each output area to avoid duplicates.', 'scanvexa-seo' ),
					esc_html( implode( ', ', $conflicts ) )
				);
				?>
			</div>
		<?php else : ?>
			<p class="scvx-field__help" style="margin-top:0;"><?php esc_html_e( 'No other active SEO plugin detected. Scanvexa SEO — AI Website Analyzer controls all areas below by default.', 'scanvexa-seo' ); ?></p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scvx-form">
			<input type="hidden" name="action" value="scvx_save_settings" />
			<input type="hidden" name="tab" value="compatibility" />
			<?php wp_nonce_field( 'scvx_settings' ); ?>

			<?php foreach ( $areas as $area => $label ) : ?>
				<div class="scvx-field scvx-field--checkbox">
					<label>
						<input type="checkbox" name="settings[control_<?php echo esc_attr( $area ); ?>]" value="1" <?php checked( ! empty( $compat[ 'control_' . $area ] ) ); ?> />
						<?php
						printf(
							/* translators: %s: output area label, e.g. "SEO Titles" */
							esc_html__( 'Scanvexa SEO — AI Website Analyzer controls %s', 'scanvexa-seo' ),
							esc_html( $label )
						);
						?>
					</label>
					<p class="scvx-field__help"><?php esc_html_e( 'Uncheck to let another active SEO plugin output this instead.', 'scanvexa-seo' ); ?></p>
				</div>
			<?php endforeach; ?>

			<button type="submit" class="scvx-button scvx-button--primary"><?php esc_html_e( 'Save Changes', 'scanvexa-seo' ); ?></button>
		</form>
		<?php
	}
}
