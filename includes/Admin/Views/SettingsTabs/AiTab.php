<?php
/**
 * "AI Providers" settings tab.
 *
 * One combined form: entering a key and clicking "Save Changes" both
 * persists and verifies it in a single step (the provider card simply
 * shows a connected/error state afterward), and the model dropdown only
 * appears once a provider is connected, fetched live from its API —
 * matching botisst-ai-chat-assistant's save-and-verify flow.
 *
 * @package Scvx\Admin\Views\SettingsTabs
 */

namespace Scvx\Admin\Views\SettingsTabs;

use Scvx\Admin\Views\View;
use Scvx\AI\AIProviderManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AiTab implements View {

	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct(
		private readonly array $values,
		private readonly ?AIProviderManager $providers
	) {}

	public function render(): void {
		$providers       = $this->providers;
		$ai_settings     = $this->values['ai'];
		$active_provider = $ai_settings['active_provider'] ?? '';
		$saved_models    = $ai_settings['models'] ?? array();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scvx-form">
			<input type="hidden" name="action" value="scvx_save_ai_providers" />
			<?php wp_nonce_field( 'scvx_settings' ); ?>

			<div class="scvx-provider-list">
				<?php foreach ( ( $providers ? $providers->get_providers() : array() ) as $provider_id => $label ) : ?>
					<?php
					$has_key   = $providers->has_api_key( $provider_id );
					$masked    = $providers->get_masked_key( $provider_id );
					$models    = $has_key ? $providers->get_models( $provider_id ) : array();
					$is_active = $active_provider === $provider_id;
					?>
					<div class="scvx-provider-card <?php echo $is_active ? 'is-active' : ''; ?>" data-provider="<?php echo esc_attr( $provider_id ); ?>">
						<div class="scvx-provider-card__header">
							<h3><?php echo esc_html( $label ); ?> API Key</h3>
							<?php if ( $has_key ) : ?>
								<span class="scvx-badge scvx-badge--success"><?php esc_html_e( 'Connected', 'scanvexa-seo' ); ?></span>
							<?php endif; ?>
							<?php if ( $is_active ) : ?>
								<span class="scvx-badge scvx-badge--active"><?php esc_html_e( 'Active', 'scanvexa-seo' ); ?></span>
							<?php endif; ?>
						</div>

						<div class="scvx-field">
							<?php if ( $has_key ) : ?>
								<input type="text" value="<?php echo esc_attr( $masked ); ?>" disabled readonly />
							<?php else : ?>
								<input type="password" name="keys[<?php echo esc_attr( $provider_id ); ?>]" autocomplete="off" placeholder="<?php esc_attr_e( 'API key', 'scanvexa-seo' ); ?>" />
							<?php endif; ?>
						</div>

						<?php if ( $has_key && ! empty( $models ) ) : ?>
							<div class="scvx-field">
								<label><?php esc_html_e( 'Model', 'scanvexa-seo' ); ?></label>
								<select name="models[<?php echo esc_attr( $provider_id ); ?>]">
									<?php foreach ( $models as $model_id => $model_label ) : ?>
										<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $saved_models[ $provider_id ] ?? '', $model_id ); ?>>
											<?php echo esc_html( $model_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						<?php endif; ?>

						<?php if ( $has_key ) : ?>
							<div class="scvx-provider-card__footer">
								<label class="scvx-radio">
									<input type="radio" name="active_provider" value="<?php echo esc_attr( $provider_id ); ?>" <?php checked( $is_active ); ?> />
									<?php esc_html_e( 'Use as active provider', 'scanvexa-seo' ); ?>
								</label>
								<button type="button" class="scvx-link scvx-link--danger scvx-remove-key" data-provider="<?php echo esc_attr( $provider_id ); ?>">
									<?php esc_html_e( 'Remove key', 'scanvexa-seo' ); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="scvx-field">
				<label for="monthly_request_limit"><?php esc_html_e( 'Monthly AI request limit', 'scanvexa-seo' ); ?></label>
				<input type="number" min="0" step="1" name="settings[monthly_request_limit]" id="monthly_request_limit"
					value="<?php echo esc_attr( (string) ( $ai_settings['monthly_request_limit'] ?? 0 ) ); ?>" />
				<p class="scvx-field__help"><?php esc_html_e( '0 = unlimited. Since you use your own API key, this is a self-imposed guardrail, not a plugin-enforced quota.', 'scanvexa-seo' ); ?></p>
			</div>

			<button type="submit" class="scvx-button scvx-button--primary"><?php esc_html_e( 'Save Changes', 'scanvexa-seo' ); ?></button>
		</form>

		<?php
		// "Remove key" behavior lives in admin/js/admin.js (enqueued by
		// Admin\Assets), driven by the scvx.adminPostUrl /
		// settingsNonce localized there — never inline here.
	}
}
