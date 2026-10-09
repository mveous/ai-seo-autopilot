<?php
/**
 * Registry + factory for AI provider adapters, and the single entry point
 * calling code should use to run an AI request. Handles provider
 * selection, API key retrieval, and usage recording so that no other
 * class needs to know about provider wiring.
 *
 * API keys are stored in the exact same options WordPress core's native
 * AI Client SDK (bundled in WP 7.0+) and its connector settings read from,
 * instead of a plugin-private option — so a key entered here is usable by
 * core and any other plugin built on the same SDK, and vice versa. See
 * get_api_key()/persist_api_key()/delete_api_key() below.
 *
 * @package AISEOAutopilot\AI
 */

namespace AISEOAutopilot\AI;

use AISEOAutopilot\AI\Providers\AnthropicProvider;
use AISEOAutopilot\AI\Providers\GeminiProvider;
use AISEOAutopilot\AI\Providers\OpenAIProvider;
use AISEOAutopilot\Features\FeatureManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIProviderManager {

	/** @var array<string,class-string<AIProviderInterface>> */
	private array $providers = array();

	private FeatureManager $features;

	public function __construct( FeatureManager $features ) {
		$this->features = $features;

		$this->register_provider( 'openai', OpenAIProvider::class );
		$this->register_provider( 'gemini', GeminiProvider::class );
		$this->register_provider( 'anthropic', AnthropicProvider::class );

		/**
		 * Allows Pro or third parties to register additional AI providers.
		 *
		 * @param AIProviderManager $manager
		 */
		do_action( 'ai_seo_autopilot/register_ai_providers', $this );
	}

	/**
	 * @param class-string<AIProviderInterface> $class
	 */
	public function register_provider( string $id, string $class ): void {
		$this->providers[ $id ] = $class;
	}

	/**
	 * @return array<string,string> provider id => label
	 */
	public function get_providers(): array {
		$labels = array();

		foreach ( $this->providers as $id => $class ) {
			// Labels don't require an API key, so instantiate with an
			// empty key purely to read static metadata.
			$instance         = new $class( '' );
			$labels[ $id ]    = $instance->get_label();
		}

		return $labels;
	}

	/**
	 * @return array<string,string> model id => label, for a given provider.
	 */
	public function get_models( string $provider_id ): array {
		return $this->get_models_for_key( $provider_id, (string) $this->get_api_key( $provider_id ) );
	}

	/**
	 * Models for a provider as seen by a specific key (providers that list
	 * models live use the key to query their API).
	 *
	 * @return array<string,string> model id => label.
	 */
	public function get_models_for_key( string $provider_id, string $api_key ): array {
		$provider = $this->make_provider( $provider_id, $api_key );

		return null === $provider ? array() : $provider->get_models();
	}

	public function has_provider( string $provider_id ): bool {
		return isset( $this->providers[ $provider_id ] );
	}

	/**
	 * Whether this provider requires Pro to connect. Every registered
	 * provider (Anthropic, OpenAI, Gemini, and any Pro-registered ones)
	 * is available on Free.
	 */
	public function is_provider_locked( string $provider_id ): bool {
		return false;
	}

	/**
	 * Instantiate a provider adapter with an API key.
	 */
	private function make_provider( string $provider_id, string $api_key ): ?AIProviderInterface {
		if ( ! isset( $this->providers[ $provider_id ] ) ) {
			return null;
		}

		$class = $this->providers[ $provider_id ];

		return new $class( $api_key );
	}

	// -------------------------------------------------------------
	// API key storage.
	// -------------------------------------------------------------

	/**
	 * Validate and persist an API key for a provider. Stored via
	 * persist_api_key() — the plaintext value never appears in a REST
	 * response, only a masked preview (see get_masked_key()).
	 *
	 * @return true|\WP_Error
	 */
	public function save_api_key( string $provider_id, string $api_key ) {
		$provider = $this->make_provider( $provider_id, $api_key );

		if ( null === $provider ) {
			return new \WP_Error( 'ai_seo_autopilot_unknown_provider', __( 'Unknown AI provider.', 'ai-seo-autopilot' ) );
		}

		if ( $this->is_provider_locked( $provider_id ) ) {
			return new \WP_Error(
				'ai_seo_autopilot_provider_locked',
				__( 'This AI provider requires AI SEO Autopilot Pro. The Free plan is limited to Anthropic Claude.', 'ai-seo-autopilot' )
			);
		}

		$api_key = trim( $api_key );
		if ( '' === $api_key ) {
			return new \WP_Error( 'ai_seo_autopilot_empty_key', __( 'API key cannot be empty.', 'ai-seo-autopilot' ) );
		}

		$valid = $provider->validate_api_key( $api_key );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$this->persist_api_key( $provider_id, $api_key );

		return true;
	}

	public function delete_api_key( string $provider_id ): void {
		$connector_id = self::connector_id( $provider_id );

		if ( self::has_native_ai_client() ) {
			delete_option( 'connectors_ai_' . $connector_id . '_api_key' );
			return;
		}

		$creds = get_option( 'wp_ai_client_provider_credentials', array() );
		if ( isset( $creds[ $connector_id ] ) ) {
			unset( $creds[ $connector_id ] );
			update_option( 'wp_ai_client_provider_credentials', $creds );
		}
	}

	public function has_api_key( string $provider_id ): bool {
		return null !== $this->get_api_key( $provider_id );
	}

	/**
	 * A masked preview of the stored key, safe to expose in the admin UI
	 * (e.g. "sk-...a1B2"). Never returns the plaintext key.
	 */
	public function get_masked_key( string $provider_id ): ?string {
		$key = $this->get_api_key( $provider_id );

		if ( null === $key ) {
			return null;
		}

		$len = strlen( $key );
		if ( $len <= 8 ) {
			return str_repeat( '•', max( 4, $len ) );
		}

		return substr( $key, 0, 3 ) . str_repeat( '•', 6 ) . substr( $key, -4 );
	}

	/**
	 * Store a provider's API key in the exact option WordPress core's
	 * native AI Client SDK (WP 7.0+) reads from, or — on older WP, where
	 * core doesn't bundle the SDK — the location the standalone SDK
	 * package itself uses, so the bundled AI Client adapters in
	 * includes/AI/Providers pick it up without any plugin-private copy.
	 */
	private function persist_api_key( string $provider_id, string $api_key ): void {
		$connector_id = self::connector_id( $provider_id );
		$api_key      = sanitize_text_field( $api_key );

		if ( self::has_native_ai_client() ) {
			update_option( 'connectors_ai_' . $connector_id . '_api_key', $api_key );
			return;
		}

		$creds                  = get_option( 'wp_ai_client_provider_credentials', array() );
		$creds[ $connector_id ] = $api_key;
		update_option( 'wp_ai_client_provider_credentials', $creds );
	}

	private function get_api_key( string $provider_id ): ?string {
		$connector_id = self::connector_id( $provider_id );

		if ( self::has_native_ai_client() ) {
			$key = (string) get_option( 'connectors_ai_' . $connector_id . '_api_key', '' );
		} else {
			$creds = get_option( 'wp_ai_client_provider_credentials', array() );
			$key   = isset( $creds[ $connector_id ] ) ? (string) $creds[ $connector_id ] : '';
		}

		return '' === $key ? null : $key;
	}

	/**
	 * Whether WordPress core provides the AI Client SDK natively. Mirrors
	 * the check the wp-ai-client package's own functions.php uses.
	 */
	private static function has_native_ai_client(): bool {
		return function_exists( 'wp_ai_client_prompt' );
	}

	/**
	 * Maps this plugin's own provider identifiers to the AI Client SDK's
	 * provider IDs, which is what both core's connector options and the
	 * standalone SDK's credentials array are keyed by. Only "gemini"
	 * differs — the SDK (and WP core) know Google's provider as "google".
	 */
	private static function connector_id( string $provider_id ): string {
		return 'gemini' === $provider_id ? 'google' : $provider_id;
	}

	// -------------------------------------------------------------
	// Active provider selection.
	// -------------------------------------------------------------

	public function get_active_provider_id(): string {
		$settings = get_option( 'ai_seo_autopilot_settings', array() );

		return (string) ( $settings['ai']['active_provider'] ?? '' );
	}

	public function is_configured(): bool {
		$id = $this->get_active_provider_id();

		return '' !== $id && $this->has_api_key( $id );
	}

	/**
	 * The model saved for a provider in Settings → AI Providers, or '' if
	 * none has been chosen yet (callers fall back to the provider's own
	 * default in that case).
	 */
	public function get_selected_model( string $provider_id ): string {
		$settings = get_option( 'ai_seo_autopilot_settings', array() );

		return (string) ( $settings['ai']['models'][ $provider_id ] ?? '' );
	}

	// -------------------------------------------------------------
	// Generation.
	// -------------------------------------------------------------

	/**
	 * Run an AI request using the site's active provider/key.
	 *
	 * @param array{model?:string,temperature?:float,max_tokens?:int,json?:bool} $options
	 *
	 * @return AIResponse|\WP_Error
	 */
	public function generate( string $feature, string $system_prompt, string $user_prompt, array $options = array() ) {
		$provider_id = $options['provider'] ?? $this->get_active_provider_id();

		if ( '' === $provider_id ) {
			return new \WP_Error( 'ai_seo_autopilot_no_provider', __( 'No AI provider is configured. Add an API key in AI SEO → Settings → AI Providers.', 'ai-seo-autopilot' ) );
		}

		if ( $this->is_provider_locked( $provider_id ) ) {
			return new \WP_Error(
				'ai_seo_autopilot_provider_locked',
				__( 'This AI provider requires AI SEO Autopilot Pro. Switch to Anthropic Claude, or upgrade to Pro.', 'ai-seo-autopilot' )
			);
		}

		$api_key = $this->get_api_key( $provider_id );

		if ( null === $api_key ) {
			return new \WP_Error( 'ai_seo_autopilot_no_key', __( 'No API key is stored for the selected AI provider.', 'ai-seo-autopilot' ) );
		}

		$provider = $this->make_provider( $provider_id, $api_key );

		if ( null === $provider ) {
			return new \WP_Error( 'ai_seo_autopilot_unknown_provider', __( 'Unknown AI provider.', 'ai-seo-autopilot' ) );
		}

		if ( empty( $options['model'] ) ) {
			$selected_model = $this->get_selected_model( $provider_id );

			if ( '' !== $selected_model ) {
				$options['model'] = $selected_model;
			}
		}

		$response = $provider->generate( $system_prompt, $user_prompt, $options );

		/**
		 * Fires after every AI request, success or failure, so usage can
		 * be tracked centrally without every caller doing it manually.
		 *
		 * @param AIResponse|\WP_Error $response
		 * @param string               $feature
		 * @param string               $provider_id
		 */
		do_action( 'ai_seo_autopilot/ai_request_completed', $response, $feature, $provider_id );

		return $response;
	}
}
