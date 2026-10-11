<?php
/**
 * Registers all REST controllers under the aasa/v1 namespace.
 *
 * @package Aasa\REST
 */

namespace Aasa\REST;

use Aasa\Core\Plugin;
use Aasa\Core\Registrable;
use Aasa\REST\Controllers\AIController;
use Aasa\REST\Controllers\AnalyzeController;
use Aasa\REST\Controllers\DashboardController;
use Aasa\REST\Controllers\ScanController;
use Aasa\REST\Controllers\SchemaController;
use Aasa\REST\Controllers\SettingsController;

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
		 * Allows third parties to inject additional REST controllers into
		 * the same bootstrap pass.
		 *
		 * @param array<int,object> $controllers
		 * @param Plugin             $plugin
		 */
		$controllers = (array) apply_filters( 'aasa/rest_controllers', $controllers, $this->plugin );

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
