<?php
/**
 * "AI Providers" settings tab.
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
		?>
		<div class="ai-seo-provider-list">
			<?php foreach ( ( $providers ? $providers->get_providers() : array() ) as $provider_id => $label ) : ?>
				<?php
				$has_key   = $providers->has_api_key( $provider_id );
				$masked    = $providers->get_masked_key( $provider_id );
				$models    = $providers->get_models( $provider_id );
				$is_active = $active_provider === $provider_id;
				$is_locked = $providers->is_provider_locked( $provider_id );
				?>
				<div class="ai-seo-provider-card <?php echo $is_active ? 'is-active' : ''; ?> <?php echo $is_locked ? 'is-locked' : ''; ?>">
					<div class="ai-seo-provider-card__header">
						<h3><?php echo esc_html( $label ); ?></h3>
						<?php if ( $is_locked ) : ?>
							<span class="ai-seo-badge ai-seo-badge--pro"><?php esc_html_e( 'PRO', 'ai-seo-autopilot' ); ?></span>
						<?php endif; ?>
						<?php if ( $has_key ) : ?>
							<span class="ai-seo-badge ai-seo-badge--success"><?php esc_html_e( 'Connected', 'ai-seo-autopilot' ); ?></span>
						<?php endif; ?>
						<?php if ( $is_active ) : ?>
							<span class="ai-seo-badge ai-seo-badge--active"><?php esc_html_e( 'Active', 'ai-seo-autopilot' ); ?></span>
						<?php endif; ?>
					</div>

					<?php if ( $has_key ) : ?>
						<p class="ai-seo-provider-card__key"><?php echo esc_html( $masked ); ?></p>
					<?php endif; ?>

					<?php if ( $is_locked ) : ?>
						<p class="ai-seo-provider-card__locked-note">
							<?php esc_html_e( 'Free is limited to Anthropic Claude. Upgrade to Pro to connect this provider.', 'ai-seo-autopilot' ); ?>
						</p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-autopilot-upgrade' ) ); ?>" class="ai-seo-button ai-seo-button--secondary">
							<?php esc_html_e( 'Unlock with Pro', 'ai-seo-autopilot' ); ?>
						</a>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-seo-form ai-seo-form--inline">
							<input type="hidden" name="action" value="ai_seo_autopilot_save_api_key" />
							<input type="hidden" name="provider" value="<?php echo esc_attr( $provider_id ); ?>" />
							<?php wp_nonce_field( 'ai_seo_autopilot_settings' ); ?>

							<input type="password" name="api_key" autocomplete="off" placeholder="<?php echo esc_attr( $has_key ? __( 'Leave blank to keep the current key', 'ai-seo-autopilot' ) : __( 'API key', 'ai-seo-autopilot' ) ); ?>" />

							<select name="model">
								<?php foreach ( $models as $model_id => $model_label ) : ?>
									<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $ai_settings['model'] ?? '', $model_id ); ?>>
										<?php echo esc_html( $model_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>

							<button type="submit" class="ai-seo-button ai-seo-button--secondary">
								<?php echo esc_html( $has_key ? __( 'Update & Activate', 'ai-seo-autopilot' ) : __( 'Connect', 'ai-seo-autopilot' ) ); ?>
							</button>
						</form>

						<?php if ( $has_key ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-seo-form ai-seo-form--inline">
								<input type="hidden" name="action" value="ai_seo_autopilot_delete_api_key" />
								<input type="hidden" name="provider" value="<?php echo esc_attr( $provider_id ); ?>" />
								<?php wp_nonce_field( 'ai_seo_autopilot_settings' ); ?>
								<button type="submit" class="ai-seo-link ai-seo-link--danger" onclick="return confirm('<?php echo esc_js( __( 'Remove this API key?', 'ai-seo-autopilot' ) ); ?>');">
									<?php esc_html_e( 'Remove key', 'ai-seo-autopilot' ); ?>
								</button>
							</form>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-seo-form">
			<input type="hidden" name="action" value="ai_seo_autopilot_save_settings" />
			<input type="hidden" name="tab" value="ai" />
			<?php wp_nonce_field( 'ai_seo_autopilot_settings' ); ?>

			<div class="ai-seo-field">
				<label for="monthly_request_limit"><?php esc_html_e( 'Monthly AI request limit', 'ai-seo-autopilot' ); ?></label>
				<input type="number" min="0" step="1" name="settings[monthly_request_limit]" id="monthly_request_limit"
					value="<?php echo esc_attr( (string) ( $ai_settings['monthly_request_limit'] ?? 0 ) ); ?>" />
				<p class="ai-seo-field__help"><?php esc_html_e( '0 = unlimited. Since you use your own API key, this is a self-imposed guardrail, not a plugin-enforced quota.', 'ai-seo-autopilot' ); ?></p>
			</div>

			<button type="submit" class="ai-seo-button ai-seo-button--primary"><?php esc_html_e( 'Save Changes', 'ai-seo-autopilot' ); ?></button>

			<script>
				( function () {
					var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
					var nonce = <?php echo wp_json_encode( wp_create_nonce( 'ai_seo_autopilot_settings' ) ); ?>;
					var loadingText = <?php echo wp_json_encode( __( 'Loading models…', 'ai-seo-autopilot' ) ); ?>;

					document.querySelectorAll( '.ai-seo-provider-card form' ).forEach( function ( form ) {
						var keyInput = form.querySelector( 'input[name="api_key"]' );
						var select = form.querySelector( 'select[name="model"]' );
						var provider = form.querySelector( 'input[name="provider"]' );
						var timer;

						if ( ! keyInput || ! select || ! provider ) {
							return;
						}

						function load() {
							var key = keyInput.value.trim();
							if ( key.length < 10 ) {
								return;
							}

							var previous = select.value;
							var body = new URLSearchParams( {
								action: 'ai_seo_autopilot_fetch_models',
								_wpnonce: nonce,
								provider: provider.value,
								api_key: key
							} );

							var originalHtml = select.innerHTML;
							select.disabled = true;
							select.innerHTML = '<option>' + loadingText + '</option>';

							fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
								.then( function ( r ) { return r.json(); } )
								.then( function ( res ) {
									if ( ! res.success || ! res.data.models ) {
										select.innerHTML = originalHtml;
										return;
									}
									select.innerHTML = '';
									Object.keys( res.data.models ).forEach( function ( id ) {
										var opt = document.createElement( 'option' );
										opt.value = id;
										opt.textContent = res.data.models[ id ];
										opt.selected = id === previous;
										select.appendChild( opt );
									} );
								} )
								.catch( function () { select.innerHTML = originalHtml; } )
								.finally( function () { select.disabled = false; } );
						}

						keyInput.addEventListener( 'input', function () {
							clearTimeout( timer );
							timer = setTimeout( load, 600 );
						} );
					} );
				}() );
			</script>
		</form>
		<?php
	}
}
