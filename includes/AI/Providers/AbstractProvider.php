<?php
/**
 * Shared HTTP plumbing for AI provider adapters.
 *
 * @package AISEOAutopilot\AI\Providers
 */

namespace AISEOAutopilot\AI\Providers;

use AISEOAutopilot\AI\AIProviderInterface;
use AISEOAutopilot\AI\AIResponse;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractProvider implements AIProviderInterface {

	protected string $api_key;

	/**
	 * Whether the bundled WordPress AI Client SDK has been wired up to run
	 * over wp_remote_request() (and WP object cache) for this request yet.
	 */
	private static bool $ai_client_bootstrapped = false;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	public function get_default_model(): string {
		$models = $this->get_models();

		return (string) array_key_first( $models );
	}

	/**
	 * Attempts to run a completion through the WordPress AI Client SDK
	 * (the officially recommended integration path, since it's what
	 * WordPress core itself uses starting in 7.0 via wp_ai_client_prompt()).
	 *
	 * Returns null whenever the SDK path can't be used for any reason
	 * (not installed/autoloaded, unknown model, provider rejects the
	 * request while resolving model metadata, transport failure, etc.),
	 * so the caller falls back to its own direct wp_safe_remote_post()
	 * implementation. That fallback is what keeps the plugin working
	 * identically on every WordPress version it supports (6.0+), not
	 * just 7.0+, and is why the direct HTTP code below still exists
	 * alongside this bridge rather than replacing it outright.
	 *
	 * @param array{model?:string,temperature?:float,max_tokens?:int,json?:bool} $options
	 *
	 * @return AIResponse|\WP_Error|null
	 */
	protected function generate_via_ai_client(
		string $system_prompt,
		string $user_prompt,
		array $options,
		string $ai_client_provider_class,
		string $ai_client_model_id,
		RequestAuthenticationInterface $authentication
	) {
		if ( ! self::bootstrap_ai_client() || ! class_exists( $ai_client_provider_class ) ) {
			return null;
		}

		try {
			$registry    = AiClient::defaultRegistry();
			$provider_id = $ai_client_provider_class::metadata()->getId();

			if ( ! $registry->hasProvider( $provider_id ) ) {
				$registry->registerProvider( $ai_client_provider_class );
			}

			$registry->setProviderRequestAuthentication( $provider_id, $authentication );

			$model = $ai_client_provider_class::model( $ai_client_model_id );

			$prompt = wp_ai_client_prompt( $user_prompt )
				->using_model( $model )
				->using_system_instruction( $system_prompt )
				->using_temperature( (float) ( $options['temperature'] ?? 0.4 ) )
				->using_max_tokens( (int) ( $options['max_tokens'] ?? 800 ) );

			if ( ! empty( $options['json'] ) ) {
				$prompt = $prompt->as_json_response();
			}

			$result = $prompt->generate_text_result();
		} catch ( \Throwable $e ) {
			return null;
		}

		if ( is_wp_error( $result ) ) {
			return null;
		}

		$candidates = $result->getCandidates();

		if ( empty( $candidates ) ) {
			return null;
		}

		$content = '';
		foreach ( $candidates[0]->getMessage()->getParts() as $part ) {
			$content .= (string) $part->getText();
		}

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
			model: $ai_client_model_id,
			raw: method_exists( $result, 'toArray' ) ? $result->toArray() : array()
		);
	}

	/**
	 * Wires the bundled AI Client SDK to WordPress's own HTTP and object
	 * cache APIs, exactly once per request. Skipped on WordPress 7.0+,
	 * where core provides (and has already wired up) its own copy.
	 */
	private static function bootstrap_ai_client(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		if ( self::$ai_client_bootstrapped ) {
			return true;
		}

		if (
			( ! function_exists( 'wp_has_ai_client' ) || ! wp_has_ai_client() )
			&& class_exists( \WordPress\AI_Client\HTTP\WP_AI_Client_Discovery_Strategy::class )
		) {
			\WordPress\AI_Client\HTTP\WP_AI_Client_Discovery_Strategy::init();

			if ( class_exists( \WordPress\AI_Client\Cache\WordPress_Cache::class ) ) {
				AiClient::setCache( new \WordPress\AI_Client\Cache\WordPress_Cache() );
			}
		}

		self::$ai_client_bootstrapped = true;

		return true;
	}

	/**
	 * Perform a JSON POST request with consistent timeout/error handling.
	 * Uses wp_safe_remote_post so requests can never be redirected to
	 * internal/private network addresses (SSRF hardening), even though
	 * the endpoint itself is a hardcoded provider host, not user input.
	 *
	 * @param array<string,mixed> $body
	 * @param array<string,string> $headers
	 *
	 * @return array<string,mixed>|\WP_Error Decoded JSON body, or WP_Error.
	 */
	protected function post_json( string $url, array $body, array $headers ) {
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array_merge(
					array( 'Content-Type' => 'application/json' ),
					$headers
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_network_error',
				__( 'Could not reach the AI provider. Please check your network connection and try again.', 'ai-seo-autopilot' ),
				array( 'original' => $response->get_error_message() )
			);
		}

		$code          = (int) wp_remote_retrieve_response_code( $response );
		$raw_body      = wp_remote_retrieve_body( $response );
		$decoded       = json_decode( $raw_body, true );

		if ( 429 === $code ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_rate_limited',
				__( 'The AI provider is rate-limiting requests. Please wait and try again.', 'ai-seo-autopilot' )
			);
		}

		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_auth_error',
				__( 'The AI provider rejected the request. Please check that your API key is valid and has the required permissions.', 'ai-seo-autopilot' )
			);
		}

		if ( $code >= 400 ) {
			$message = is_array( $decoded ) ? self::extract_error_message( $decoded ) : '';

			return new \WP_Error(
				'ai_seo_autopilot_ai_provider_error',
				$message ? $message : __( 'The AI provider returned an unexpected error.', 'ai-seo-autopilot' ),
				array( 'status' => $code )
			);
		}

		if ( ! is_array( $decoded ) ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_invalid_response',
				__( 'The AI provider returned a response that could not be parsed.', 'ai-seo-autopilot' )
			);
		}

		return $decoded;
	}

	/**
	 * @param array<string,mixed> $decoded
	 */
	private static function extract_error_message( array $decoded ): string {
		if ( isset( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
			return sanitize_text_field( $decoded['error']['message'] );
		}
		if ( isset( $decoded['message'] ) && is_string( $decoded['message'] ) ) {
			return sanitize_text_field( $decoded['message'] );
		}

		return '';
	}
}
