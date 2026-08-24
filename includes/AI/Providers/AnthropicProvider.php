<?php
/**
 * Anthropic Claude (Messages API) adapter.
 *
 * @package AISEOAutopilot\AI\Providers
 */

namespace AISEOAutopilot\AI\Providers;

use AISEOAutopilot\AI\AIResponse;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AnthropicProvider extends AbstractProvider {

	private const API_BASE     = 'https://api.anthropic.com/v1';
	private const API_VERSION  = '2023-06-01';

	public function get_id(): string {
		return 'anthropic';
	}

	public function get_label(): string {
		return __( 'Anthropic Claude', 'ai-seo-autopilot' );
	}

	public function get_models(): array {
		return array(
			'claude-sonnet-5' => 'Claude Sonnet 5',
			'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
			'claude-opus-5'   => 'Claude Opus 5',
		);
	}

	public function generate( string $system_prompt, string $user_prompt, array $options = array() ) {
		$model = $options['model'] ?? $this->get_default_model();

		if ( ! empty( $options['json'] ) ) {
			$system_prompt .= "\n\n" . __( 'Respond with valid JSON only. Do not include markdown code fences or any commentary outside the JSON object.', 'ai-seo-autopilot' );
		}

		$body = array(
			'model'      => $model,
			'system'     => $system_prompt,
			'max_tokens' => $options['max_tokens'] ?? 800,
			'temperature' => $options['temperature'] ?? 0.4,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
		);

		$decoded = $this->post_json(
			self::API_BASE . '/messages',
			$body,
			array(
				'x-api-key'         => $this->api_key,
				'anthropic-version' => self::API_VERSION,
			)
		);

		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$content = $decoded['content'][0]['text'] ?? '';

		if ( '' === $content ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_empty_response',
				__( 'The AI provider returned an empty response.', 'ai-seo-autopilot' )
			);
		}

		return new AIResponse(
			content: (string) $content,
			prompt_tokens: (int) ( $decoded['usage']['input_tokens'] ?? 0 ),
			completion_tokens: (int) ( $decoded['usage']['output_tokens'] ?? 0 ),
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
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'ai_seo_autopilot_ai_network_error', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_invalid_key',
				__( 'This Anthropic API key could not be verified.', 'ai-seo-autopilot' )
			);
		}

		return true;
	}
}
