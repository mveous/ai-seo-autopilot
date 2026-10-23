<?php
/**
 * Value object returned by every AI provider adapter.
 *
 * @package Scvx\AI
 */

namespace Scvx\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIResponse {

	public function __construct(
		public string $content,
		public int $prompt_tokens = 0,
		public int $completion_tokens = 0,
		public string $provider = '',
		public string $model = '',
		public array $raw = array()
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
