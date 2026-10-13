<?php
/**
 * Registers all REST controllers under the scvx/v1 namespace.
 *
 * @package Scvx\REST
 */

namespace Scvx\REST;

use Scvx\Core\Plugin;
use Scvx\Core\Registrable;
use Scvx\REST\Controllers\AIController;
use Scvx\REST\Controllers\AnalyzeController;
use Scvx\REST\Controllers\DashboardController;
use Scvx\REST\Controllers\ScanController;
use Scvx\REST\Controllers\SchemaController;
use Scvx\REST\Controllers\SettingsController;

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
		$controllers = (array) apply_filters( 'scvx/rest_controllers', $controllers, $this->plugin );

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
