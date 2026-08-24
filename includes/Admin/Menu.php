<?php
/**
 * Registers the wp-admin menu pages and delegates rendering to view
 * templates in admin/views/.
 *
 * @package AISEOAutopilot\Admin
 */

namespace AISEOAutopilot\Admin;

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

		$this->render_view( 'dashboard', array( 'features' => $this->features ) );
	}

	public function render_settings(): void {
		if ( ! current_user_can( $this->capability( 'manage_settings' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-seo-autopilot' ) );
		}

		/** @var Settings|null $settings */
		$settings = Plugin::instance()->get( 'admin.settings' );

		$this->render_view(
			'settings',
			array(
				'features' => $this->features,
				'settings' => $settings,
			)
		);
	}

	public function render_upgrade(): void {
		if ( ! current_user_can( $this->capability( 'view_dashboard' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-seo-autopilot' ) );
		}

		$this->render_view( 'upgrade', array( 'features' => $this->features ) );
	}

	/**
	 * @param array<string,mixed> $vars
	 */
	private function render_view( string $view, array $vars = array() ): void {
		$path = AI_SEO_AUTOPILOT_DIR . 'admin/views/' . $view . '.php';

		if ( ! is_readable( $path ) ) {
			return;
		}

		// Extracted for template ergonomics; keys are hardcoded above, not
		// user input, so this is not an injection vector.
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		require $path;
	}

	private function capability( string $action ): string {
		/** @var CapabilityManager|null $capabilities */
		$capabilities = Plugin::instance()->get( 'capabilities' );

		return $capabilities ? $capabilities->capability_for( $action ) : 'manage_options';
	}
}
