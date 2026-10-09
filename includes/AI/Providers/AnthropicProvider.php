<?php
/**
 * Anthropic Claude adapter, backed by the bundled WordPress AI Client SDK's
 * Anthropic provider.
 *
 * @package AISEOAutopilot\AI\Providers
 */

namespace AISEOAutopilot\AI\Providers;

use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
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

	protected function ai_client_provider_class(): string
	{
		return AiClientAnthropicProvider::class;
	}

	protected function make_authentication(string $api_key): RequestAuthenticationInterface
	{
		return new AnthropicApiKeyRequestAuthentication($api_key);
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
}
