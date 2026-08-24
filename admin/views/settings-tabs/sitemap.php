<?php
/**
 * @package AISEOAutopilot
 * @var array<string,mixed> $values
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is require()'d inside a method's local scope (see Menu::render_view()), not evaluated as a standalone global scope.

$sitemap     = $values['sitemap'];
$excluded    = (array) ( $sitemap['exclude_post_types'] ?? array() );
$post_types  = get_post_types( array( 'public' => true ), 'objects' );
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-seo-form">
	<input type="hidden" name="action" value="ai_seo_autopilot_save_settings" />
	<input type="hidden" name="tab" value="sitemap" />
	<?php wp_nonce_field( 'ai_seo_autopilot_settings' ); ?>

	<div class="ai-seo-field ai-seo-field--checkbox">
		<label>
			<input type="checkbox" name="settings[enabled]" value="1" <?php checked( ! empty( $sitemap['enabled'] ) ); ?> />
			<?php esc_html_e( 'Enable XML sitemap', 'ai-seo-autopilot' ); ?>
		</label>
	</div>

	<?php if ( ! empty( $sitemap['enabled'] ) ) : ?>
		<p class="ai-seo-field__help">
			<?php
			printf(
				/* translators: %s: sitemap URL */
				esc_html__( 'Your sitemap index: %s', 'ai-seo-autopilot' ),
				'<code>' . esc_html( home_url( '/sitemap.xml' ) ) . '</code>'
			);
			?>
		</p>
	<?php endif; ?>

	<div class="ai-seo-field">
		<span class="ai-seo-field__label"><?php esc_html_e( 'Exclude post types', 'ai-seo-autopilot' ); ?></span>
		<?php foreach ( $post_types as $post_type ) : ?>
			<label class="ai-seo-checkbox-inline">
				<input type="checkbox" name="settings[exclude_post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>"
					<?php checked( in_array( $post_type->name, $excluded, true ) ); ?> />
				<?php echo esc_html( $post_type->labels->name ); ?>
			</label>
		<?php endforeach; ?>
	</div>

	<button type="submit" class="ai-seo-button ai-seo-button--primary"><?php esc_html_e( 'Save Changes', 'ai-seo-autopilot' ); ?></button>
</form>
