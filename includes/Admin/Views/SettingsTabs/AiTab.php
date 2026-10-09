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
 * @package AISEOAutopilot\Admin\Views\SettingsTabs
 */

namespace AISEOAutopilot\Admin\Views\SettingsTabs;

use AISEOAutopilot\Admin\Views\View;
use AISEOAutopilot\AI\AIProviderManager;

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
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-seo-form">
			<input type="hidden" name="action" value="ai_seo_autopilot_save_ai_providers" />
			<?php wp_nonce_field( 'ai_seo_autopilot_settings' ); ?>

			<div class="ai-seo-provider-list">
				<?php foreach ( ( $providers ? $providers->get_providers() : array() ) as $provider_id => $label ) : ?>
					<?php
					$has_key   = $providers->has_api_key( $provider_id );
					$masked    = $providers->get_masked_key( $provider_id );
					$models    = $has_key ? $providers->get_models( $provider_id ) : array();
					$is_active = $active_provider === $provider_id;
					?>
					<div class="ai-seo-provider-card <?php echo $is_active ? 'is-active' : ''; ?>" data-provider="<?php echo esc_attr( $provider_id ); ?>">
						<div class="ai-seo-provider-card__header">
							<h3><?php echo esc_html( $label ); ?> API Key</h3>
							<?php if ( $has_key ) : ?>
								<span class="ai-seo-badge ai-seo-badge--success"><?php esc_html_e( 'Connected', 'ai-seo-autopilot' ); ?></span>
							<?php endif; ?>
							<?php if ( $is_active ) : ?>
								<span class="ai-seo-badge ai-seo-badge--active"><?php esc_html_e( 'Active', 'ai-seo-autopilot' ); ?></span>
							<?php endif; ?>
						</div>

						<div class="ai-seo-field">
							<?php if ( $has_key ) : ?>
								<input type="text" value="<?php echo esc_attr( $masked ); ?>" disabled readonly />
							<?php else : ?>
								<input type="password" name="keys[<?php echo esc_attr( $provider_id ); ?>]" autocomplete="off" placeholder="<?php esc_attr_e( 'API key', 'ai-seo-autopilot' ); ?>" />
							<?php endif; ?>
						</div>

						<?php if ( $has_key && ! empty( $models ) ) : ?>
							<div class="ai-seo-field">
								<label><?php esc_html_e( 'Model', 'ai-seo-autopilot' ); ?></label>
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
							<div class="ai-seo-provider-card__footer">
								<label class="ai-seo-radio">
									<input type="radio" name="active_provider" value="<?php echo esc_attr( $provider_id ); ?>" <?php checked( $is_active ); ?> />
									<?php esc_html_e( 'Use as active provider', 'ai-seo-autopilot' ); ?>
								</label>
								<button type="button" class="ai-seo-link ai-seo-link--danger ai-seo-remove-key" data-provider="<?php echo esc_attr( $provider_id ); ?>">
									<?php esc_html_e( 'Remove key', 'ai-seo-autopilot' ); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="ai-seo-field">
				<label for="monthly_request_limit"><?php esc_html_e( 'Monthly AI request limit', 'ai-seo-autopilot' ); ?></label>
				<input type="number" min="0" step="1" name="settings[monthly_request_limit]" id="monthly_request_limit"
					value="<?php echo esc_attr( (string) ( $ai_settings['monthly_request_limit'] ?? 0 ) ); ?>" />
				<p class="ai-seo-field__help"><?php esc_html_e( '0 = unlimited. Since you use your own API key, this is a self-imposed guardrail, not a plugin-enforced quota.', 'ai-seo-autopilot' ); ?></p>
			</div>

			<button type="submit" class="ai-seo-button ai-seo-button--primary"><?php esc_html_e( 'Save Changes', 'ai-seo-autopilot' ); ?></button>
		</form>

		<script>
			( function () {
				var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-post.php' ) ); ?>;
				var nonce = <?php echo wp_json_encode( wp_create_nonce( 'ai_seo_autopilot_settings' ) ); ?>;
				var confirmText = <?php echo wp_json_encode( __( 'Remove this API key?', 'ai-seo-autopilot' ) ); ?>;

				document.querySelectorAll( '.ai-seo-remove-key' ).forEach( function ( button ) {
					button.addEventListener( 'click', function () {
						if ( ! window.confirm( confirmText ) ) {
							return;
						}

						var body = new URLSearchParams( {
							action: 'ai_seo_autopilot_delete_api_key',
							_wpnonce: nonce,
							provider: button.dataset.provider
						} );

						button.disabled = true;

						fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
							.then( function () {
								var url = new URL( window.location.href );
								url.searchParams.set( 'status', 'deleted' );
								window.location.href = url.toString();
							} )
							.catch( function () { button.disabled = false; } );
					} );
				} );
			}() );
		</script>
		<?php
	}
}
