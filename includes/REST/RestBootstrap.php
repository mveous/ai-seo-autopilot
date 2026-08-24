<?php
/**
 * Registers all REST controllers under the ai-seo/v1 namespace.
 *
 * @package AISEOAutopilot\REST
 */

namespace AISEOAutopilot\REST;

use AISEOAutopilot\Core\Plugin;
use AISEOAutopilot\Core\Registrable;
use AISEOAutopilot\REST\Controllers\AIController;
use AISEOAutopilot\REST\Controllers\AnalyzeController;
use AISEOAutopilot\REST\Controllers\DashboardController;
use AISEOAutopilot\REST\Controllers\ScanController;
use AISEOAutopilot\REST\Controllers\SchemaController;
use AISEOAutopilot\REST\Controllers\SettingsController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestBootstrap implements Registrable {

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$controllers = array(
			new DashboardController( $this->plugin ),
			new ScanController( $this->plugin ),
			new AnalyzeController( $this->plugin ),
			new AIController( $this->plugin ),
			new SettingsController( $this->plugin ),
			new SchemaController( $this->plugin ),
		);

		/**
		 * Allows Pro to inject additional REST controllers into the same
		 * bootstrap pass.
		 *
		 * @param array<int,object> $controllers
		 * @param Plugin             $plugin
		 */
		$controllers = (array) apply_filters( 'ai_seo_autopilot/rest_controllers', $controllers, $this->plugin );

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
