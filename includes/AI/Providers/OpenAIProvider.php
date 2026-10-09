<?php
/**
 * OpenAI adapter, backed by the bundled WordPress AI Client SDK's OpenAI
 * provider.
 *
 * @package AISEOAutopilot\AI\Providers
 */

namespace AISEOAutopilot\AI\Providers;

use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
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

	protected function ai_client_provider_class(): string {
		return AiClientOpenAiProvider::class;
	}

	protected function make_authentication( string $api_key ): RequestAuthenticationInterface {
		return new ApiKeyRequestAuthentication( $api_key );
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
}
