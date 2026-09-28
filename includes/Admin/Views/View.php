<?php
/**
 * Contract every admin-page renderer implements.
 *
 * @package AISEOAutopilot\Admin\Views
 */

namespace AISEOAutopilot\Admin\Views;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface View {

	/**
	 * Emits this view's HTML directly (echo, not return), matching how
	 * WP admin page callbacks are expected to behave.
	 */
	public function render(): void;
}
