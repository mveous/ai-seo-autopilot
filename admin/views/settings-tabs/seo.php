<?php
/**
 * @package AISEOAutopilot
 * @var array<string,mixed> $values
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is require()'d inside a method's local scope (see Menu::render_view()), not evaluated as a standalone global scope.

$seo = $values['seo'];
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-seo-form">
	<input type="hidden" name="action" value="ai_seo_autopilot_save_settings" />
	<input type="hidden" name="tab" value="seo" />
	<?php wp_nonce_field( 'ai_seo_autopilot_settings' ); ?>

	<div class="ai-seo-field">
		<label for="title_separator"><?php esc_html_e( 'Title separator', 'ai-seo-autopilot' ); ?></label>
		<select name="settings[title_separator]" id="title_separator">
			<?php foreach ( array( '-', '|', '·', '•', '—' ) as $sep ) : ?>
				<option value="<?php echo esc_attr( $sep ); ?>" <?php selected( $seo['title_separator'], $sep ); ?>><?php echo esc_html( $sep ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="ai-seo-field ai-seo-field--checkbox">
		<label>
			<input type="checkbox" name="settings[noindex_paginated]" value="1" <?php checked( ! empty( $seo['noindex_paginated'] ) ); ?> />
			<?php esc_html_e( 'Noindex paginated archives (page 2, 3, …)', 'ai-seo-autopilot' ); ?>
		</label>
	</div>

	<div class="ai-seo-field ai-seo-field--checkbox">
		<label>
			<input type="checkbox" name="settings[noindex_archives]" value="1" <?php checked( ! empty( $seo['noindex_archives'] ) ); ?> />
			<?php esc_html_e( 'Noindex date and author archives', 'ai-seo-autopilot' ); ?>
		</label>
	</div>

	<div class="ai-seo-field ai-seo-field--checkbox">
		<label>
			<input type="checkbox" name="settings[strip_category_base]" value="1" <?php checked( ! empty( $seo['strip_category_base'] ) ); ?> />
			<?php esc_html_e( 'Remove /category/ base from category URLs', 'ai-seo-autopilot' ); ?>
		</label>
	</div>

	<button type="submit" class="ai-seo-button ai-seo-button--primary"><?php esc_html_e( 'Save Changes', 'ai-seo-autopilot' ); ?></button>
</form>
