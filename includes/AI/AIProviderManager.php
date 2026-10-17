<?php
/**
 * Registry + factory for AI provider adapters, and the single entry point
 * calling code should use to run an AI request. Handles provider
 * selection, API key retrieval, and usage recording so that no other
 * class needs to know about provider wiring.
 *
 * Keys entered in this plugin's own Settings → AI Providers screen are
 * stored in a plugin-private option and only ever used by this plugin's
 * own adapters. This never reads from or writes to WordPress core's native
 * AI Client connector storage (the `connectors_ai_*_api_key` options) —
 * doing so would reuse a credential the site owner authorized through
 * core's own connector UI for a purpose they didn't consent to here. See
 * get_api_key()/persist_api_key()/delete_api_key() below.
 *
 * @package Scvx\AI
 */

namespace Scvx\AI;

use Scvx\AI\Providers\AnthropicProvider;
use Scvx\AI\Providers\GeminiProvider;
use Scvx\AI\Providers\OpenAIProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIProviderManager {

	/** @var array<string,class-string<AIProviderInterface>> */
	private array $providers = array();

	public function __construct() {
		$this->register_provider( 'openai', OpenAIProvider::class );
		$this->register_provider( 'gemini', GeminiProvider::class );
		$this->register_provider( 'anthropic', AnthropicProvider::class );

		/**
		 * Allows third parties to register additional AI providers.
		 *
		 * @param AIProviderManager $manager
		 */
		do_action( 'scvx/register_ai_providers', $this );
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
			return new \WP_Error( 'scvx_unknown_provider', __( 'Unknown AI provider.', 'scanvexa-seo' ) );
		}

		$api_key = trim( $api_key );
		if ( '' === $api_key ) {
			return new \WP_Error( 'scvx_empty_key', __( 'API key cannot be empty.', 'scanvexa-seo' ) );
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
	 * Store a provider's API key in this plugin's own option, keyed by the
	 * AI Client SDK's provider id so the bundled adapters in
	 * includes/AI/Providers can look it up directly. This is always a
	 * plugin-private store — it never touches WordPress core's native AI
	 * Client connector options, even when core provides that SDK natively.
	 */
	private function persist_api_key( string $provider_id, string $api_key ): void {
		$connector_id = self::connector_id( $provider_id );
		$api_key      = sanitize_text_field( $api_key );

		$creds                  = get_option( 'wp_ai_client_provider_credentials', array() );
		$creds[ $connector_id ] = $api_key;
		update_option( 'wp_ai_client_provider_credentials', $creds );
	}

	private function get_api_key( string $provider_id ): ?string {
		$connector_id = self::connector_id( $provider_id );

		$creds = get_option( 'wp_ai_client_provider_credentials', array() );
		$key   = isset( $creds[ $connector_id ] ) ? (string) $creds[ $connector_id ] : '';

		return '' === $key ? null : $key;
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
		$settings = get_option( 'scvx_settings', array() );

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
		$settings = get_option( 'scvx_settings', array() );

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
			return new \WP_Error( 'scvx_no_provider', __( 'No AI provider is configured. Add an API key in AI SEO → Settings → AI Providers.', 'scanvexa-seo' ) );
		}

		$api_key = $this->get_api_key( $provider_id );

		if ( null === $api_key ) {
			return new \WP_Error( 'scvx_no_key', __( 'No API key is stored for the selected AI provider.', 'scanvexa-seo' ) );
		}

		$provider = $this->make_provider( $provider_id, $api_key );

		if ( null === $provider ) {
			return new \WP_Error( 'scvx_unknown_provider', __( 'Unknown AI provider.', 'scanvexa-seo' ) );
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
		do_action( 'scvx/ai_request_completed', $response, $feature, $provider_id );

		return $response;
	}
}
