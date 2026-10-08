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

	public function get_id(): string {
		return 'gemini';
	}

	public function get_label(): string {
		return __( 'Google Gemini', 'ai-seo-autopilot' );
	}

	public function get_models(): array {
		return array(
			'gemini-3.8-flash' => 'Gemini 3.8 Flash',
			'gemini-2.5-flash' => 'Gemini 2.5 Flash',
			'gemini-2.5-pro'   => 'Gemini 2.5 Pro',
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
		if ( ! class_exists( GoogleApiKeyRequestAuthentication::class ) ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_client_missing',
				__( 'The bundled AI Client SDK could not be loaded.', 'ai-seo-autopilot' )
			);
		}

		$result = $this->complete_via_ai_client(
			'Respond with the word OK only.',
			'Test the Gemini API connection.',
			array( 'max_tokens' => 5 ),
			AiClientGoogleProvider::class,
			new GoogleApiKeyRequestAuthentication( $api_key )
		);

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_invalid_key',
				__( 'This Gemini API key could not be verified.', 'ai-seo-autopilot' ),
				array( 'previous_error' => $result )
			);
		}

		return true;
	}
}
