<?php
/**
 * Contract every admin-page renderer implements.
 *
 * @package Scvx\Admin\Views
 */

namespace Scvx\Admin\Views;

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
