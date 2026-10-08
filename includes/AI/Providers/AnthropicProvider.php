<?php
/**
 * Anthropic Claude adapter, backed by the bundled WordPress AI Client SDK's
 * Anthropic provider.
 *
 * @package AISEOAutopilot\AI\Providers
 */

namespace AISEOAutopilot\AI\Providers;

use WordPress\AnthropicAiProvider\Authentication\AnthropicApiKeyRequestAuthentication;
use WordPress\AnthropicAiProvider\Provider\AnthropicProvider as AiClientAnthropicProvider;

if (!defined('ABSPATH')) {
	exit;
}

final class AnthropicProvider extends AbstractProvider
{

	public function get_id(): string
	{
		return 'anthropic';
	}

	public function get_label(): string
	{
		return __('Anthropic Claude', 'ai-seo-autopilot');
	}

	public function get_models(): array
	{
		return array(
			'claude-sonnet-5' => 'Claude Sonnet 5',
			'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
			'claude-opus-5' => 'Claude Opus 5',
		);
	}

	public function generate(string $system_prompt, string $user_prompt, array $options = array())
	{
		if (!class_exists(AnthropicApiKeyRequestAuthentication::class)) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_client_missing',
				__('The bundled AI Client SDK could not be loaded. Try running "composer install" inside the plugin\'s includes/ai-providers directory.', 'ai-seo-autopilot')
			);
		}

		if (!empty($options['json'])) {
			$system_prompt .= "\n\n" . __('Respond with valid JSON only. Do not include markdown code fences or any commentary outside the JSON object.', 'ai-seo-autopilot');
		}

		return $this->complete_via_ai_client(
			$system_prompt,
			$user_prompt,
			$options,
			AiClientAnthropicProvider::class,
			new AnthropicApiKeyRequestAuthentication($this->api_key)
		);
	}

	public function validate_api_key(string $api_key)
	{
		if (!class_exists(AnthropicApiKeyRequestAuthentication::class)) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_client_missing',
				__(
					'The bundled AI Client SDK could not be loaded.',
					'ai-seo-autopilot'
				)
			);
		}

		$authentication = new AnthropicApiKeyRequestAuthentication($api_key);

		$result = $this->complete_via_ai_client(
			'Respond with the word OK only.',
			'Test the Anthropic API connection.',
			array(
				'max_tokens' => 5,
			),
			AiClientAnthropicProvider::class,
			$authentication
		);

		if (is_wp_error($result)) {
			return new \WP_Error(
				'ai_seo_autopilot_ai_invalid_key',
				__(
					'This Anthropic API key could not be verified.',
					'ai-seo-autopilot'
				),
				array(
					'previous_error' => $result,
				)
			);
		}

		return true;
	}
}
