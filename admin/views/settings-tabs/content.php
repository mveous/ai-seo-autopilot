<?php
/**
 * @package AISEOAutopilot
 * @var array<string,mixed> $values
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is require()'d inside a method's local scope (see Menu::render_view()), not evaluated as a standalone global scope.

$content = $values['content'];
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-seo-form">
	<input type="hidden" name="action" value="ai_seo_autopilot_save_settings" />
	<input type="hidden" name="tab" value="content" />
	<?php wp_nonce_field( 'ai_seo_autopilot_settings' ); ?>

	<div class="ai-seo-field ai-seo-field--checkbox">
		<label>
			<input type="checkbox" name="settings[focus_keyword_enabled]" value="1" <?php checked( ! empty( $content['focus_keyword_enabled'] ) ); ?> />
			<?php esc_html_e( 'Enable focus keyword field and keyword-based content analysis in the editor', 'ai-seo-autopilot' ); ?>
		</label>
	</div>

	<button type="submit" class="ai-seo-button ai-seo-button--primary"><?php esc_html_e( 'Save Changes', 'ai-seo-autopilot' ); ?></button>
</form>
