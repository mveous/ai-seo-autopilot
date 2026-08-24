<?php
/**
 * @package AISEOAutopilot
 * @var array<string,mixed> $values
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is require()'d inside a method's local scope (see Menu::render_view()), not evaluated as a standalone global scope.

$advanced = $values['advanced'];
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-seo-form">
	<input type="hidden" name="action" value="ai_seo_autopilot_save_settings" />
	<input type="hidden" name="tab" value="advanced" />
	<?php wp_nonce_field( 'ai_seo_autopilot_settings' ); ?>

	<div class="ai-seo-field ai-seo-field--checkbox">
		<label>
			<input type="checkbox" name="settings[remove_data_on_uninstall]" value="1" <?php checked( ! empty( $advanced['remove_data_on_uninstall'] ) ); ?> />
			<?php esc_html_e( 'Remove all plugin data when the plugin is deleted', 'ai-seo-autopilot' ); ?>
		</label>
		<p class="ai-seo-field__help"><?php esc_html_e( 'Deletes all scans, issues, activity history, redirects, AI usage records and SEO metadata. This cannot be undone.', 'ai-seo-autopilot' ); ?></p>
	</div>

	<button type="submit" class="ai-seo-button ai-seo-button--primary"><?php esc_html_e( 'Save Changes', 'ai-seo-autopilot' ); ?></button>
</form>
