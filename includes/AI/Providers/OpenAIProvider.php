<?php
/**
 * OpenAI adapter, backed by the bundled WordPress AI Client SDK's OpenAI
 * provider.
 *
 * @package AISEOAutopilot\AI\Providers
 */

namespace AISEOAutopilot\AI\Providers;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\OpenAiAiProvider\Provider\OpenAiProvider as AiClientOpenAiProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OpenAIProvider extends AbstractProvider {

	private const API_BASE = 'https://api.openai.com/v1';

	public function get_id(): string {
		return 'openai';
	}

	public function get_label(): string {
		return __( 'OpenAI', 'ai-seo-autopilot' );
	}

	public function get_models(): array {
		return array(
			'gpt-4o-mini'  => 'GPT-4o mini',
			'gpt-4o'       => 'GPT-4o',
			'gpt-4.1-mini' => 'GPT-4.1 mini',
			'gpt-4.1'      => 'GPT-4.1',
		);
	}

	public function generate( string $system_prompt, string $user_prompt, array $options = array() ) {
		if ( ! class_exists( ApiKeyRequestAuthentication::class ) ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_client_missing',
				__( 'The bundled AI Client SDK could not be loaded. Try running "composer install" inside the plugin\'s includes/ai-providers directory.', 'ai-seo-autopilot' )
			);
		}

		return $this->complete_via_ai_client(
			$system_prompt,
			$user_prompt,
			$options,
			AiClientOpenAiProvider::class,
			new ApiKeyRequestAuthentication( $this->api_key )
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
