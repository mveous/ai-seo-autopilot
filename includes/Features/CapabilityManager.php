<?php
/**
 * Maps plugin actions to WordPress capabilities. Centralizing this means
 * a site owner can remap "who is allowed to do what" via a single filter
 * instead of hunting through every controller for a current_user_can() call.
 *
 * @package AISEOAutopilot\Features
 */

namespace AISEOAutopilot\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CapabilityManager {

	/** @var array<string,string> action => default WP capability */
	private array $map = array(
		'manage_settings'   => 'manage_options',
		'view_dashboard'    => 'edit_posts',
		'run_scan'          => 'manage_options',
		'edit_seo_meta'     => 'edit_posts',
		'use_ai_generation' => 'edit_posts',
		'manage_redirects'  => 'manage_options',
		'approve_actions'   => 'manage_options',
		'manage_license'    => 'manage_options',
	);

	/**
	 * @return string[]
	 */
	public function actions(): array {
		return array_keys( $this->map );
	}

	public function capability_for( string $action ): string {
		$default = $this->map[ $action ] ?? 'manage_options';

		/**
		 * Filters the WP capability required for a given plugin action.
		 *
		 * @param string $capability
		 * @param string $action
		 */
		return (string) apply_filters( 'ai_seo_autopilot/capability', $default, $action );
	}

	public function current_user_can( string $action ): bool {
		return current_user_can( $this->capability_for( $action ) );
	}

	/**
	 * Verify capability and die with a structured error if missing.
	 * Intended for REST permission_callback use.
	 */
	public function rest_permission( string $action ): bool {
		return $this->current_user_can( $action );
	}
}
