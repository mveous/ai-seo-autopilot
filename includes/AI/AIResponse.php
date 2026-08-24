<?php
/**
 * Value object returned by every AI provider adapter.
 *
 * @package AISEOAutopilot\AI
 */

namespace AISEOAutopilot\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIResponse {

	public function __construct(
		public readonly string $content,
		public readonly int $prompt_tokens = 0,
		public readonly int $completion_tokens = 0,
		public readonly string $provider = '',
		public readonly string $model = '',
		public readonly array $raw = array()
	) {}

	public function total_tokens(): int {
		return $this->prompt_tokens + $this->completion_tokens;
	}

	/**
	 * Attempt to decode the content as JSON (providers are instructed to
	 * return structured JSON for anything that will drive an action).
	 * Returns null if the content is not valid JSON.
	 */
	public function json(): ?array {
		$decoded = json_decode( trim( $this->content ), true );

		return is_array( $decoded ) ? $decoded : null;
	}
}
