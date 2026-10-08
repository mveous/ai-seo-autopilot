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

	/**
	 * Offline fallback only; Google retires Gemini models often, so the live
	 * list from the API (see fetch_live_models()) is preferred.
	 */
	public function get_models(): array {
		$live = $this->fetch_live_models();

		if ( ! empty( $live ) ) {
			return $live;
		}

		return array(
			'gemini-3.8-flash' => 'Gemini 3.8 Flash',
		);
	}

	/**
	 * Lists text-generation Gemini models available to this API key, cached.
	 *
	 * @return array<string,string> Model ID => label; empty on any failure.
	 */
	private function fetch_live_models(): array {
		if ( '' === $this->api_key ) {
			return array();
		}

		$cache_key = 'ai_seo_autopilot_gemini_models_' . md5( $this->api_key );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_safe_remote_get(
			self::API_BASE . '/models?pageSize=100',
			array(
				'timeout' => 10,
				'headers' => array( 'x-goog-api-key' => $this->api_key ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$models = array();

		foreach ( (array) ( $body['models'] ?? array() ) as $model ) {
			$id = isset( $model['name'] ) ? preg_replace( '#^models/#', '', (string) $model['name'] ) : '';

			if (
				0 !== strpos( $id, 'gemini-' )
				|| ! in_array( 'generateContent', (array) ( $model['supportedGenerationMethods'] ?? array() ), true )
				|| preg_match( '/embed|image|tts|audio|live|vision|exp|preview|latest/', $id )
			) {
				continue;
			}

			$models[ $id ] = (string) ( $model['displayName'] ?? $id );
		}

		// Newest first so the default (first entry) is the latest model.
		krsort( $models, SORT_NATURAL );

		if ( ! empty( $models ) ) {
			set_transient( $cache_key, $models, DAY_IN_SECONDS );
		}

		return $models;
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
