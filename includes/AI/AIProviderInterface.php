<?php
/**
 * Contract every AI provider adapter must implement. New providers can be
 * added without touching any calling code — they just register themselves
 * with AIProviderManager.
 *
 * @package AISEOAutopilot\AI
 */

namespace AISEOAutopilot\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AIProviderInterface {

	/**
	 * Stable machine identifier, e.g. 'openai'.
	 */
	public function get_id(): string;

	/**
	 * Human-readable label, e.g. 'OpenAI'.
	 */
	public function get_label(): string;

	/**
	 * Models this provider supports, as returned live by the provider's own
	 * API for the key this instance was constructed with (not a static,
	 * curated list) — empty if no key is set or the lookup fails.
	 *
	 * @return array<string,string> model id => human label
	 */
	public function get_models(): array;

	public function get_default_model(): string;

	/**
	 * Run a completion request.
	 *
	 * @param string               $system_prompt System / instruction prompt.
	 * @param string               $user_prompt   User / task prompt.
	 * @param array{model?:string,temperature?:float,max_tokens?:int,json?:bool} $options
	 *
	 * @return AIResponse|\WP_Error
	 */
	public function generate( string $system_prompt, string $user_prompt, array $options = array() );

	/**
	 * Verify an API key by making a minimal, low-cost request.
	 *
	 * @return true|\WP_Error
	 */
	public function validate_api_key( string $api_key );
}
