<?php
/**
 * Registers the wp-admin menu pages and delegates rendering to the
 * class-based views in Aasa\Admin\Views.
 *
 * @package Aasa\Admin
 */

namespace Aasa\Admin;

use Aasa\Admin\Views\DashboardView;
use Aasa\Admin\Views\IssuesView;
use Aasa\Admin\Views\SettingsView;
use Aasa\Core\Plugin;
use Aasa\Core\Registrable;
use Aasa\Features\CapabilityManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Menu implements Registrable {

	public const DASHBOARD_SLUG = 'ai-autopilot-seo-assistant';
	public const SETTINGS_SLUG  = 'ai-autopilot-seo-assistant-settings';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	public function add_menu(): void {
		$capability = $this->capability( 'view_dashboard' );

		add_menu_page(
			__( 'AI Autopilot SEO Assistant', 'ai-autopilot-seo-assistant' ),
			__( 'AI SEO', 'ai-autopilot-seo-assistant' ),
			$capability,
			self::DASHBOARD_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-superhero-alt',
			80
		);

		add_submenu_page(
			self::DASHBOARD_SLUG,
			__( 'Dashboard', 'ai-autopilot-seo-assistant' ),
			__( 'Dashboard', 'ai-autopilot-seo-assistant' ),
			$capability,
			self::DASHBOARD_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::DASHBOARD_SLUG,
			__( 'Settings', 'ai-autopilot-seo-assistant' ),
			__( 'Settings', 'ai-autopilot-seo-assistant' ),
			$this->capability( 'manage_settings' ),
			self::SETTINGS_SLUG,
			array( $this, 'render_settings' )
		);
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( $this->capability( 'view_dashboard' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-autopilot-seo-assistant' ) );
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-autopilot-seo-assistant' ) );
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
