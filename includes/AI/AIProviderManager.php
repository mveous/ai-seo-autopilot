<?php
/**
 * Registry + factory for AI provider adapters, and the single entry point
 * calling code should use to run an AI request. Handles provider
 * selection, API key retrieval/decryption, and usage recording so that
 * no other class needs to know about encryption or provider wiring.
 *
 * @package AISEOAutopilot\AI
 */

namespace AISEOAutopilot\AI;

use AISEOAutopilot\AI\Providers\AnthropicProvider;
use AISEOAutopilot\AI\Providers\GeminiProvider;
use AISEOAutopilot\AI\Providers\OpenAIProvider;
use AISEOAutopilot\Features\FeatureManager;
use AISEOAutopilot\Security\Encryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIProviderManager {

	private const KEYS_OPTION = 'ai_seo_autopilot_api_keys';

	/**
	 * The only provider Free users can connect. Pro unlocks the rest via
	 * the `ai_multi_provider` feature gate.
	 */
	public const FREE_PROVIDER = 'anthropic';

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
		if ( ! isset( $this->providers[ $provider_id ] ) ) {
			return array();
		}

		$class    = $this->providers[ $provider_id ];
		$instance = new $class( '' );

		return $instance->get_models();
	}

	public function has_provider( string $provider_id ): bool {
		return isset( $this->providers[ $provider_id ] );
	}

	/**
	 * Whether this provider requires Pro to connect. Free is limited to
	 * Anthropic Claude; Pro unlocks every registered provider (including
	 * ones Pro itself registers via `ai_seo_autopilot/register_ai_providers`).
	 */
	public function is_provider_locked( string $provider_id ): bool {
		if ( self::FREE_PROVIDER === $provider_id ) {
			return false;
		}

		return ! $this->features->is_available( 'ai_multi_provider' );
	}

	/**
	 * Instantiate a provider adapter with a decrypted API key.
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
	 * Validate and persist an API key for a provider. The key is only
	 * ever stored encrypted; the plaintext value never touches the
	 * database, logs, or a REST response.
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

		$stored                = get_option( self::KEYS_OPTION, array() );
		$stored[ $provider_id ] = Encryption::encrypt( $api_key );

		update_option( self::KEYS_OPTION, $stored, false );

		return true;
	}

	public function delete_api_key( string $provider_id ): void {
		$stored = get_option( self::KEYS_OPTION, array() );
		unset( $stored[ $provider_id ] );
		update_option( self::KEYS_OPTION, $stored, false );
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

		return null === $key ? null : Encryption::mask( $key );
	}

	private function get_api_key( string $provider_id ): ?string {
		$stored = get_option( self::KEYS_OPTION, array() );

		if ( empty( $stored[ $provider_id ] ) ) {
			return null;
		}

		return Encryption::decrypt( (string) $stored[ $provider_id ] );
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
