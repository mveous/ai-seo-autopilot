<?php
/**
 * Shared plumbing for AI provider adapters. Every request is executed
 * through the bundled WordPress AI Client SDK (see AISEOAutopilot\AI\AIClient
 * for how it's loaded and registered).
 *
 * @package AISEOAutopilot\AI\Providers
 */

namespace AISEOAutopilot\AI\Providers;

use AISEOAutopilot\AI\AIProviderInterface;
use AISEOAutopilot\AI\AIResponse;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractProvider implements AIProviderInterface {

	protected string $api_key;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * The AI Client SDK provider class backing this adapter.
	 *
	 * @return class-string<\WordPress\AiClient\Providers\Contracts\ProviderInterface>
	 */
	abstract protected function ai_client_provider_class(): string;

	/**
	 * Builds the SDK authentication object for a given API key.
	 */
	abstract protected function make_authentication( string $api_key ): RequestAuthenticationInterface;

	public function get_default_model(): string {
		$models = $this->get_models();

		return (string) array_key_first( $models );
	}

	/**
	 * Live model list for this provider, as seen by $this->api_key.
	 * Fetched from the provider's real API (not a static/curated list) and
	 * cached in a transient keyed by provider + key, since it only changes
	 * when the key changes or the provider adds/removes models.
	 *
	 * @return array<string,string> model id => human label
	 */
	public function get_models(): array {
		if ( '' === $this->api_key ) {
			return array();
		}

		$provider_class = $this->ai_client_provider_class();

		if ( ! class_exists( AiClient::class ) || ! class_exists( $provider_class ) ) {
			return array();
		}

		$cache_key = 'ai_seo_autopilot_models_' . $this->get_id() . '_' . md5( $this->api_key );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		try {
			$this->register_and_authenticate( $provider_class, $this->make_authentication( $this->api_key ) );

			$models = array();

			foreach ( $provider_class::modelMetadataDirectory()->listModelMetadata() as $model ) {
				$models[ $model->getId() ] = $model->getName();
			}
		} catch ( \Throwable $e ) {
			return array();
		}

		set_transient( $cache_key, $models, HOUR_IN_SECONDS );

		return $models;
	}

	/**
	 * Verify an API key by attempting to list the provider's available
	 * models — the same low-cost probe WordPress core's own AI connector
	 * settings use, rather than running an actual completion (which can
	 * fail for reasons unrelated to the key, e.g. a "thinking" model
	 * consuming its whole token budget on hidden reasoning).
	 *
	 * @return true|\WP_Error
	 */
	public function validate_api_key( string $api_key ) {
		$provider_class = $this->ai_client_provider_class();

		if ( ! class_exists( AiClient::class ) || ! class_exists( $provider_class ) ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_client_missing',
				__( 'The bundled AI Client SDK could not be loaded. Try running "composer install" inside the plugin\'s includes/ai-providers directory.', 'ai-seo-autopilot' )
			);
		}

		try {
			$this->register_and_authenticate( $provider_class, $this->make_authentication( $api_key ) );

			$models = $provider_class::modelMetadataDirectory()->listModelMetadata();

			if ( empty( $models ) ) {
				return new \WP_Error(
					'ai_seo_autopilot_ai_invalid_key',
					/* translators: %s: AI provider label, e.g. "OpenAI". */
					sprintf( __( 'Invalid or unauthorized API key for %s.', 'ai-seo-autopilot' ), $this->get_label() )
				);
			}
		} catch ( \Throwable $e ) {
			$message = strtolower( $e->getMessage() );

			foreach ( array( '401', '403', 'incorrect api key', 'unauthorized', 'invalid', 'key not found' ) as $needle ) {
				if ( false !== strpos( $message, $needle ) ) {
					return new \WP_Error(
						'ai_seo_autopilot_ai_invalid_key',
						/* translators: %s: AI provider label, e.g. "OpenAI". */
						sprintf( __( 'Invalid API key for %s. Please check your credentials.', 'ai-seo-autopilot' ), $this->get_label() )
					);
				}
			}

			return new \WP_Error( 'ai_seo_autopilot_ai_client_error', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Registers the provider with the shared SDK registry (if not already)
	 * and attaches this request's authentication, returning its provider id.
	 */
	private function register_and_authenticate( string $provider_class, RequestAuthenticationInterface $authentication ): string {
		$registry    = AiClient::defaultRegistry();
		$provider_id = $provider_class::metadata()->getId();

		if ( ! $registry->hasProvider( $provider_id ) ) {
			$registry->registerProvider( $provider_class );
		}

		$registry->setProviderRequestAuthentication( $provider_id, $authentication );

		return $provider_id;
	}

	/**
	 * Runs a completion through the bundled WordPress AI Client SDK.
	 *
	 * @param array{model?:string,temperature?:float,max_tokens?:int,json?:bool} $options
	 * @param class-string<\WordPress\AiClient\Providers\Contracts\ProviderInterface> $ai_client_provider_class
	 *
	 * @return AIResponse|\WP_Error
	 */
	protected function complete_via_ai_client(
		string $system_prompt,
		string $user_prompt,
		array $options,
		string $ai_client_provider_class,
		RequestAuthenticationInterface $authentication
	) {
		if ( ! class_exists( AiClient::class ) || ! class_exists( $ai_client_provider_class ) ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_client_missing',
				__( 'The bundled AI Client SDK could not be loaded. Try running "composer install" inside the plugin\'s includes/ai-providers directory.', 'ai-seo-autopilot' )
			);
		}

		$model = (string) ( $options['model'] ?? $this->get_default_model() );

		try {
			$provider_id    = $this->register_and_authenticate( $ai_client_provider_class, $authentication );
			$registry       = AiClient::defaultRegistry();
			$model_instance = $registry->getProviderModel( $provider_id, $model );

			$prompt = AiClient::prompt( $user_prompt )
				->usingModel( $model_instance )
				->usingSystemInstruction( $system_prompt )
				->usingTemperature( (float) ( $options['temperature'] ?? 0.4 ) )
				->usingMaxTokens( (int) ( $options['max_tokens'] ?? 800 ) )
				->usingRequestOptions( RequestOptions::fromArray( array( RequestOptions::KEY_TIMEOUT => 60 ) ) );

			if ( ! empty( $options['json'] ) ) {
				$prompt = $prompt->asJsonResponse();
			}

			$result = $prompt->generateTextResult();
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_client_error',
				$e->getMessage(),
				array( 'status' => self::guess_error_status( $e->getMessage() ) )
			);
		}

		$content = trim( $result->toText() );

		if ( '' === $content ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_empty_response',
				__( 'The AI provider returned an empty response.', 'ai-seo-autopilot' )
			);
		}

		$usage = $result->getTokenUsage();

		return new AIResponse(
			content: $content,
			prompt_tokens: $usage->getPromptTokens(),
			completion_tokens: $usage->getCompletionTokens(),
			provider: $this->get_id(),
			model: $model,
			raw: $result->toArray()
		);
	}

	/**
	 * The SDK surfaces every transport/provider failure as a generic
	 * exception, so status codes are inferred from the message text to
	 * keep the rate-limit/auth-error handling callers already rely on.
	 */
	private static function guess_error_status( string $message ): int {
		$message = strtolower( $message );

		foreach ( array( '429', 'quota', 'rate limit', 'rate_limit', 'resource_exhausted', 'resource has been exhausted', 'too many requests' ) as $needle ) {
			if ( false !== strpos( $message, $needle ) ) {
				return 429;
			}
		}

		foreach ( array( '401', '403', 'unauthorized', 'invalid api key', 'invalid x-api-key', 'authentication' ) as $needle ) {
			if ( false !== strpos( $message, $needle ) ) {
				return 401;
			}
		}

		return 500;
	}
}
