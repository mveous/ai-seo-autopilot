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

	public function get_default_model(): string {
		$models = $this->get_models();

		return (string) array_key_first( $models );
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
			$registry    = AiClient::defaultRegistry();
			$provider_id = $ai_client_provider_class::metadata()->getId();

			if ( ! $registry->hasProvider( $provider_id ) ) {
				$registry->registerProvider( $ai_client_provider_class );
			}

			$registry->setProviderRequestAuthentication( $provider_id, $authentication );

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
