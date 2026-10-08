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
		if ( ! class_exists( ApiKeyRequestAuthentication::class ) ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_client_missing',
				__( 'The bundled AI Client SDK could not be loaded.', 'ai-seo-autopilot' )
			);
		}

		$result = $this->complete_via_ai_client(
			'Respond with the word OK only.',
			'Test the OpenAI API connection.',
			array( 'max_tokens' => 5 ),
			AiClientOpenAiProvider::class,
			new ApiKeyRequestAuthentication( $api_key )
		);

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_invalid_key',
				__( 'This OpenAI API key could not be verified.', 'ai-seo-autopilot' ),
				array( 'previous_error' => $result )
			);
		}

		return true;
	}
}
