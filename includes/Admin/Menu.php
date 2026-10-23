<?php
/**
 * Registers the wp-admin menu pages and delegates rendering to the
 * class-based views in Scvx\Admin\Views.
 *
 * @package Scvx\Admin
 */

namespace Scvx\Admin;

use Scvx\Admin\Views\DashboardView;
use Scvx\Admin\Views\IssuesView;
use Scvx\Admin\Views\SettingsView;
use Scvx\Core\Plugin;
use Scvx\Core\Registrable;
use Scvx\Features\CapabilityManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Menu implements Registrable {

	public const DASHBOARD_SLUG = 'scanvexa-seo';
	public const SETTINGS_SLUG  = 'scanvexa-seo-settings';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	public function add_menu(): void {
		$capability = $this->capability( 'view_dashboard' );

		add_menu_page(
			__( 'Scanvexa SEO — AI Website Analyzer', 'scanvexa-seo' ),
			__( 'Scanvexa SEO', 'scanvexa-seo' ),
			$capability,
			self::DASHBOARD_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-superhero-alt',
			80
		);

		add_submenu_page(
			self::DASHBOARD_SLUG,
			__( 'Dashboard', 'scanvexa-seo' ),
			__( 'Dashboard', 'scanvexa-seo' ),
			$capability,
			self::DASHBOARD_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::DASHBOARD_SLUG,
			__( 'Settings', 'scanvexa-seo' ),
			__( 'Settings', 'scanvexa-seo' ),
			$this->capability( 'manage_settings' ),
			self::SETTINGS_SLUG,
			array( $this, 'render_settings' )
		);
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( $this->capability( 'view_dashboard' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'scanvexa-seo' ) );
		}

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch, no state change.

		if ( 'issues' === $view ) {
			( new IssuesView() )->render();
			return;
		}

		( new DashboardView() )->render();
	}

	public function render_settings(): void {
		if ( ! current_user_can( $this->capability( 'manage_settings' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'scanvexa-seo' ) );
		}

		/** @var Settings|null $settings */
		$settings = Plugin::instance()->get( 'admin.settings' );

		if ( ! $settings ) {
			return;
		}

		( new SettingsView( $settings ) )->render();
	}

	private function capability( string $action ): string {
		/** @var CapabilityManager|null $capabilities */
		$capabilities = Plugin::instance()->get( 'capabilities' );

		return $capabilities ? $capabilities->capability_for( $action ) : 'manage_options';
	}
}
