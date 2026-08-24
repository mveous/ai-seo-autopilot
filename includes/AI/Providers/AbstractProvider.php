<?php
/**
 * Shared HTTP plumbing for AI provider adapters.
 *
 * @package AISEOAutopilot\AI\Providers
 */

namespace AISEOAutopilot\AI\Providers;

use AISEOAutopilot\AI\AIProviderInterface;

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
