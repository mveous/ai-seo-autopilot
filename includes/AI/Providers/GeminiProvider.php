<?php
/**
 * Google Gemini adapter, backed by the bundled WordPress AI Client SDK's
 * Google provider.
 *
 * @package AISEOAutopilot\AI\Providers
 */

namespace AISEOAutopilot\AI\Providers;

use WordPress\GoogleAiProvider\Authentication\GoogleApiKeyRequestAuthentication;
use WordPress\GoogleAiProvider\Provider\GoogleProvider as AiClientGoogleProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GeminiProvider extends AbstractProvider {

	private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

	public function get_id(): string {
		return 'gemini';
	}

	public function get_label(): string {
		return __( 'Google Gemini', 'ai-seo-autopilot' );
	}

	public function get_models(): array {
		return array(
			'gemini-2.0-flash' => 'Gemini 2.0 Flash',
			'gemini-1.5-flash' => 'Gemini 1.5 Flash',
			'gemini-1.5-pro'   => 'Gemini 1.5 Pro',
		);
	}

	public function generate( string $system_prompt, string $user_prompt, array $options = array() ) {
		if ( ! class_exists( GoogleApiKeyRequestAuthentication::class ) ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_client_missing',
				__( 'The bundled AI Client SDK could not be loaded. Try running "composer install" inside the plugin\'s includes/ai-providers directory.', 'ai-seo-autopilot' )
			);
		}

		return $this->complete_via_ai_client(
			$system_prompt,
			$user_prompt,
			$options,
			AiClientGoogleProvider::class,
			new GoogleApiKeyRequestAuthentication( $this->api_key )
		);
	}

	public function validate_api_key( string $api_key ) {
		// Header-based auth keeps the key out of URLs (and therefore out of
		// server access logs), unlike the ?key= query param.
		$response = wp_safe_remote_get(
			self::API_BASE . '/models',
			array(
				'timeout' => 15,
				'headers' => array( 'x-goog-api-key' => $api_key ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'ai_seo_autopilot_ai_network_error', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_invalid_key',
				__( 'This Gemini API key could not be verified.', 'ai-seo-autopilot' )
			);
		}

		return true;
	}
}
