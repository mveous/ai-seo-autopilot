<?php
/**
 * OpenAI (Chat Completions) adapter.
 *
 * @package AISEOAutopilot\AI\Providers
 */

namespace AISEOAutopilot\AI\Providers;

use AISEOAutopilot\AI\AIResponse;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\OpenAiAiProvider\Provider\OpenAiProvider as AiClientOpenAiProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OpenAIProvider extends AbstractProvider {

	// Retained as the fallback transport for WordPress < 7.0 (this plugin
	// supports 6.0+) and for when the bundled AI Client SDK can't service
	// a request; see AbstractProvider::generate_via_ai_client().
	private const API_BASE = 'https://api.openai.com/v1';

	public function get_id(): string {
		return 'openai';
	}

	public function get_label(): string {
		return __( 'OpenAI', 'ai-seo-autopilot' );
	}

	public function get_models(): array {
		return array(
			'gpt-4o-mini' => 'GPT-4o mini',
			'gpt-4o'      => 'GPT-4o',
			'gpt-4.1-mini' => 'GPT-4.1 mini',
			'gpt-4.1'     => 'GPT-4.1',
		);
	}

	public function generate( string $system_prompt, string $user_prompt, array $options = array() ) {
		$model = $options['model'] ?? $this->get_default_model();

		$via_ai_client = $this->generate_via_ai_client(
			$system_prompt,
			$user_prompt,
			$options,
			AiClientOpenAiProvider::class,
			$model,
			new ApiKeyRequestAuthentication( $this->api_key )
		);

		if ( null !== $via_ai_client ) {
			return $via_ai_client;
		}

		$body = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
			'temperature' => $options['temperature'] ?? 0.4,
			'max_tokens'  => $options['max_tokens'] ?? 800,
		);

		if ( ! empty( $options['json'] ) ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}

		$decoded = $this->post_json(
			self::API_BASE . '/chat/completions',
			$body,
			array( 'Authorization' => 'Bearer ' . $this->api_key )
		);

		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$content = $decoded['choices'][0]['message']['content'] ?? '';

		if ( '' === $content ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_empty_response',
				__( 'The AI provider returned an empty response.', 'ai-seo-autopilot' )
			);
		}

		return new AIResponse(
			content: (string) $content,
			prompt_tokens: (int) ( $decoded['usage']['prompt_tokens'] ?? 0 ),
			completion_tokens: (int) ( $decoded['usage']['completion_tokens'] ?? 0 ),
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
				'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'ai_seo_autopilot_ai_network_error', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_invalid_key',
				__( 'This OpenAI API key could not be verified.', 'ai-seo-autopilot' )
			);
		}

		return true;
	}
}
