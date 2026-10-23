<?php
/**
 * Google Gemini adapter, backed by the bundled WordPress AI Client SDK's
 * Google provider.
 *
 * @package Scvx\AI\Providers
 */

namespace Scvx\AI\Providers;

use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
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
		return __( 'Google Gemini', 'scanvexa-seo' );
	}

	protected function ai_client_provider_class(): string {
		return AiClientGoogleProvider::class;
	}

	protected function make_authentication( string $api_key ): RequestAuthenticationInterface {
		return new GoogleApiKeyRequestAuthentication( $api_key );
	}

	public function generate( string $system_prompt, string $user_prompt, array $options = array() ) {
		if ( ! class_exists( GoogleApiKeyRequestAuthentication::class ) ) {
			return new \WP_Error(
				'scvx_ai_client_missing',
				__( 'The bundled AI Client SDK could not be loaded. Try running "composer install" inside the plugin\'s includes/ai-providers directory.', 'scanvexa-seo' )
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
}
