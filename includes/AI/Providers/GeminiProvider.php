<?php
/**
 * Google Gemini (Generative Language API) adapter.
 *
 * @package AISEOAutopilot\AI\Providers
 */

namespace AISEOAutopilot\AI\Providers;

use AISEOAutopilot\AI\AIResponse;
use WordPress\GoogleAiProvider\Authentication\GoogleApiKeyRequestAuthentication;
use WordPress\GoogleAiProvider\Provider\GoogleProvider as AiClientGoogleProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GeminiProvider extends AbstractProvider {

	// Retained as the fallback transport for WordPress < 7.0 (this plugin
	// supports 6.0+) and for when the bundled AI Client SDK can't service
	// a request; see AbstractProvider::generate_via_ai_client(). The AI
	// Client's Google provider covers the same Gemini models under its
	// 'google' provider id.
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
		$model = $options['model'] ?? $this->get_default_model();

		$via_ai_client = $this->generate_via_ai_client(
			$system_prompt,
			$user_prompt,
			$options,
			AiClientGoogleProvider::class,
			$model,
			new GoogleApiKeyRequestAuthentication( $this->api_key )
		);

		if ( null !== $via_ai_client ) {
			return $via_ai_client;
		}

		$generation_config = array(
			'temperature'     => $options['temperature'] ?? 0.4,
			'maxOutputTokens' => $options['max_tokens'] ?? 800,
		);

		if ( ! empty( $options['json'] ) ) {
			$generation_config['responseMimeType'] = 'application/json';
		}

		$body = array(
			'systemInstruction' => array(
				'parts' => array( array( 'text' => $system_prompt ) ),
			),
			'contents'          => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $user_prompt ) ),
				),
			),
			'generationConfig'  => $generation_config,
		);

		$decoded = $this->post_json(
			self::API_BASE . '/models/' . rawurlencode( $model ) . ':generateContent',
			$body,
			// Header-based auth keeps the key out of URLs (and therefore
			// out of server access logs), unlike the ?key= query param.
			array( 'x-goog-api-key' => $this->api_key )
		);

		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

		if ( '' === $content ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_empty_response',
				__( 'The AI provider returned an empty response.', 'ai-seo-autopilot' )
			);
		}

		return new AIResponse(
			content: (string) $content,
			prompt_tokens: (int) ( $decoded['usageMetadata']['promptTokenCount'] ?? 0 ),
			completion_tokens: (int) ( $decoded['usageMetadata']['candidatesTokenCount'] ?? 0 ),
			provider: $this->get_id(),
			model: $model,
			raw: $decoded
		);
	}

	public function validate_api_key( string $api_key ) {
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
