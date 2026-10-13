<?php
/**
 * "Sitemap" settings tab.
 *
 * @package Scvx\Admin\Views\SettingsTabs
 */

namespace Scvx\Admin\Views\SettingsTabs;

use Scvx\Admin\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SitemapTab implements View {

	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct( private readonly array $values ) {}

	public function render(): void {
		$sitemap    = $this->values['sitemap'];
		$excluded   = (array) ( $sitemap['exclude_post_types'] ?? array() );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scvx-form">
			<input type="hidden" name="action" value="scvx_save_settings" />
			<input type="hidden" name="tab" value="sitemap" />
			<?php wp_nonce_field( 'scvx_settings' ); ?>

			<div class="scvx-field scvx-field--checkbox">
				<label>
					<input type="checkbox" name="settings[enabled]" value="1" <?php checked( ! empty( $sitemap['enabled'] ) ); ?> />
					<?php esc_html_e( 'Enable XML sitemap', 'scanvexa-seo' ); ?>
				</label>
			</div>

			<?php if ( ! empty( $sitemap['enabled'] ) ) : ?>
				<p class="scvx-field__help">
					<?php
					printf(
						/* translators: %s: sitemap URL */
						esc_html__( 'Your sitemap index: %s', 'scanvexa-seo' ),
						'<code>' . esc_html( home_url( '/sitemap.xml' ) ) . '</code>'
					);
					?>
				</p>
			<?php endif; ?>

			<div class="scvx-field">
				<span class="scvx-field__label"><?php esc_html_e( 'Exclude post types', 'scanvexa-seo' ); ?></span>
				<?php foreach ( $post_types as $post_type ) : ?>
					<label class="scvx-checkbox-inline">
						<input type="checkbox" name="settings[exclude_post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>"
							<?php checked( in_array( $post_type->name, $excluded, true ) ); ?> />
						<?php echo esc_html( $post_type->labels->name ); ?>
					</label>
				<?php endforeach; ?>
			</div>

			<button type="submit" class="scvx-button scvx-button--primary"><?php esc_html_e( 'Save Changes', 'scanvexa-seo' ); ?></button>
		</form>
		<?php
	}
}
