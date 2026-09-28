<?php
/**
 * Registers the wp-admin menu pages and delegates rendering to the
 * class-based views in AISEOAutopilot\Admin\Views.
 *
 * @package AISEOAutopilot\Admin
 */

namespace AISEOAutopilot\Admin;

use AISEOAutopilot\Admin\Views\DashboardView;
use AISEOAutopilot\Admin\Views\IssuesView;
use AISEOAutopilot\Admin\Views\SettingsView;
use AISEOAutopilot\Admin\Views\UpgradeView;
use AISEOAutopilot\Core\Plugin;
use AISEOAutopilot\Core\Registrable;
use AISEOAutopilot\Features\CapabilityManager;
use AISEOAutopilot\Features\FeatureManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Menu implements Registrable {

	public const DASHBOARD_SLUG = 'ai-seo-autopilot';
	public const SETTINGS_SLUG  = 'ai-seo-autopilot-settings';

	private FeatureManager $features;

	public function __construct( FeatureManager $features ) {
		$this->features = $features;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	public function add_menu(): void {
		$capability = $this->capability( 'view_dashboard' );

		add_menu_page(
			__( 'AI SEO Autopilot', 'ai-seo-autopilot' ),
			__( 'AI SEO', 'ai-seo-autopilot' ),
			$capability,
			self::DASHBOARD_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-superhero-alt',
			80
		);

		add_submenu_page(
			self::DASHBOARD_SLUG,
			__( 'Dashboard', 'ai-seo-autopilot' ),
			__( 'Dashboard', 'ai-seo-autopilot' ),
			$capability,
			self::DASHBOARD_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::DASHBOARD_SLUG,
			__( 'Settings', 'ai-seo-autopilot' ),
			__( 'Settings', 'ai-seo-autopilot' ),
			$this->capability( 'manage_settings' ),
			self::SETTINGS_SLUG,
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			self::DASHBOARD_SLUG,
			__( 'Upgrade to Pro', 'ai-seo-autopilot' ),
			'<span style="color:#ffb900;">' . esc_html__( 'Upgrade to Pro', 'ai-seo-autopilot' ) . '</span>',
			$capability,
			'ai-seo-autopilot-upgrade',
			array( $this, 'render_upgrade' )
		);
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( $this->capability( 'view_dashboard' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-seo-autopilot' ) );
		}

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch, no state change.

		if ( 'issues' === $view ) {
			( new IssuesView() )->render();
			return;
		}

		( new DashboardView( $this->features ) )->render();
	}

	public function render_settings(): void {
		if ( ! current_user_can( $this->capability( 'manage_settings' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-seo-autopilot' ) );
		}

		/** @var Settings|null $settings */
		$settings = Plugin::instance()->get( 'admin.settings' );

		if ( ! $settings ) {
			return;
		}

		( new SettingsView( $this->features, $settings ) )->render();
	}

	public function render_upgrade(): void {
		if ( ! current_user_can( $this->capability( 'view_dashboard' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-seo-autopilot' ) );
		}

		( new UpgradeView( $this->features ) )->render();
	}

	private function capability( string $action ): string {
		/** @var CapabilityManager|null $capabilities */
		$capabilities = Plugin::instance()->get( 'capabilities' );

		return $capabilities ? $capabilities->capability_for( $action ) : 'manage_options';
	}
}
