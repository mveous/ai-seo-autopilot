<?php
/**
 * Records AI usage (requests, tokens, estimated cost) and enforces the
 * optional monthly request limit configured in settings.
 *
 * Because users bring their own API key, the plugin never assumes usage
 * is covered by a license — this is purely visibility + an optional
 * self-imposed guardrail.
 *
 * @package AISEOAutopilot\AI
 */

namespace AISEOAutopilot\AI;

use AISEOAutopilot\Core\Registrable;
use AISEOAutopilot\Database\Migrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UsageTracker implements Registrable {

	/**
	 * Rough $ per 1,000 tokens, split prompt/completion. Used only for an
	 * estimated-cost display; not billing-accurate.
	 *
	 * @var array<string,array<string,array{prompt:float,completion:float}>>
	 */
	private const PRICING = array(
		'openai'    => array(
			'gpt-4o-mini'  => array(
				'prompt'     => 0.00015,
				'completion' => 0.0006,
			),
			'gpt-4o'       => array(
				'prompt'     => 0.0025,
				'completion' => 0.01,
			),
			'gpt-4.1-mini' => array(
				'prompt'     => 0.0004,
				'completion' => 0.0016,
			),
			'gpt-4.1'      => array(
				'prompt'     => 0.002,
				'completion' => 0.008,
			),
		),
		'gemini'    => array(
			'gemini-2.5-flash'      => array(
				'prompt'     => 0.0003,
				'completion' => 0.0025,
			),
			'gemini-2.5-flash-lite' => array(
				'prompt'     => 0.0001,
				'completion' => 0.0004,
			),
			'gemini-2.5-pro'        => array(
				'prompt'     => 0.00125,
				'completion' => 0.01,
			),
		),
		'anthropic' => array(
			'claude-haiku-4-5-20251001' => array(
				'prompt'     => 0.0008,
				'completion' => 0.004,
			),
			'claude-sonnet-5'           => array(
				'prompt'     => 0.003,
				'completion' => 0.015,
			),
			'claude-opus-5'             => array(
				'prompt'     => 0.015,
				'completion' => 0.075,
			),
		),
	);

	public function register(): void {
		add_action( 'ai_seo_autopilot/ai_request_completed', array( $this, 'record' ), 10, 3 );
	}

	/**
	 * @param AIResponse|\WP_Error $response
	 */
	public function record( $response, string $feature, string $provider_id ): void {
		global $wpdb;

		$table = Migrator::table( 'ai_usage' );

		if ( is_wp_error( $response ) ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $table is from Migrator::table(), custom plugin table, no core API exists.
				$table,
				array(
					'provider'   => $provider_id,
					'model'      => '',
					'feature'    => $feature,
					'status'     => 'error',
					'user_id'    => get_current_user_id() ?: null,
					'created_at' => current_time( 'mysql', true ),
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s' )
			);

			return;
		}

		$cost = $this->estimate_cost( $response->provider, $response->model, $response->prompt_tokens, $response->completion_tokens );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $table is from Migrator::table(), custom plugin table, no core API exists.
			$table,
			array(
				'provider'           => $response->provider,
				'model'              => $response->model,
				'feature'            => $feature,
				'prompt_tokens'      => $response->prompt_tokens,
				'completion_tokens'  => $response->completion_tokens,
				'total_tokens'       => $response->total_tokens(),
				'estimated_cost'     => $cost,
				'status'             => 'success',
				'user_id'            => get_current_user_id() ?: null,
				'created_at'         => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s', '%d', '%s' )
		);
	}

	private function estimate_cost( string $provider, string $model, int $prompt_tokens, int $completion_tokens ): float {
		$rates = self::PRICING[ $provider ][ $model ] ?? null;

		if ( null === $rates ) {
			return 0.0;
		}

		return round(
			( $prompt_tokens / 1000 * $rates['prompt'] ) + ( $completion_tokens / 1000 * $rates['completion'] ),
			5
		);
	}

	/**
	 * Number of successful AI requests since the start of the current
	 * calendar month (UTC), used against the optional monthly limit.
	 */
	public function requests_this_month(): int {
		global $wpdb;

		$table = Migrator::table( 'ai_usage' );
		$since = gmdate( 'Y-m-01 00:00:00' );

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is from Migrator::table(), custom plugin table, no core API exists; caching is a possible future optimization.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = 'success' AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from Migrator::table(), not user input.
				$since
			)
		);
	}

	/**
	 * @return true|\WP_Error
	 */
	public function can_make_request() {
		$settings = get_option( 'ai_seo_autopilot_settings', array() );
		$limit    = (int) ( $settings['ai']['monthly_request_limit'] ?? 0 );

		if ( $limit <= 0 ) {
			return true;
		}

		if ( $this->requests_this_month() >= $limit ) {
			return new \WP_Error(
				'ai_seo_autopilot_usage_limit_reached',
				__( 'You have reached your configured monthly AI request limit. Raise or remove the limit in AI SEO → Settings → AI Providers.', 'ai-seo-autopilot' )
			);
		}

		return true;
	}

	/**
	 * @return array{requests:int,tokens:int,cost:float,by_provider:array<string,int>}
	 */
	public function get_summary( int $days = 30 ): array {
		global $wpdb;

		$table = Migrator::table( 'ai_usage' );
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is from Migrator::table(), custom plugin table, no core API exists; caching is a possible future optimization.
			$wpdb->prepare(
				"SELECT COUNT(*) AS requests, COALESCE(SUM(total_tokens),0) AS tokens, COALESCE(SUM(estimated_cost),0) AS cost
				 FROM {$table} WHERE status = 'success' AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from Migrator::table(), not user input.
				$since
			),
			ARRAY_A
		);

		$by_provider = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is from Migrator::table(), custom plugin table, no core API exists; caching is a possible future optimization.
			$wpdb->prepare(
				"SELECT provider, COUNT(*) AS requests
				 FROM {$table} WHERE status = 'success' AND created_at >= %s GROUP BY provider", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from Migrator::table(), not user input.
				$since
			),
			ARRAY_A
		);

		$provider_counts = array();
		foreach ( (array) $by_provider as $entry ) {
			$provider_counts[ $entry['provider'] ] = (int) $entry['requests'];
		}

		return array(
			'requests'    => (int) ( $row['requests'] ?? 0 ),
			'tokens'      => (int) ( $row['tokens'] ?? 0 ),
			'cost'        => (float) ( $row['cost'] ?? 0 ),
			'by_provider' => $provider_counts,
		);
	}
}
