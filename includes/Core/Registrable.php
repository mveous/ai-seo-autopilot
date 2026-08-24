<?php
/**
 * Contract for services that hook themselves into WordPress.
 *
 * @package AISEOAutopilot\Core
 */

namespace AISEOAutopilot\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Registrable {

	/**
	 * Attach WordPress hooks (actions/filters/REST/cron). Called once
	 * during boot, after all services exist, so cross-service lookups
	 * via Plugin::get() are safe inside register().
	 */
	public function register(): void;
}
