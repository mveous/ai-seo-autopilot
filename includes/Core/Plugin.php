<?php
/**
 * Main plugin orchestrator (singleton).
 *
 * @package AISEOAutopilot\Core
 */

namespace AISEOAutopilot\Core;

use AISEOAutopilot\AI\AIProviderManager;
use AISEOAutopilot\AI\PromptManager;
use AISEOAutopilot\AI\UsageTracker;
use AISEOAutopilot\Admin\Assets;
use AISEOAutopilot\Admin\Menu;
use AISEOAutopilot\Admin\Settings;
use AISEOAutopilot\Compatibility\ProBridge;
use AISEOAutopilot\Compatibility\SeoPluginDetector;
use AISEOAutopilot\Content\Analyzer\SeoAnalyzer;
use AISEOAutopilot\Cron\Scheduler;
use AISEOAutopilot\Database\Migrator;
use AISEOAutopilot\Features\CapabilityManager;
use AISEOAutopilot\Features\FeatureManager;
use AISEOAutopilot\Features\UpgradeManager;
use AISEOAutopilot\REST\RestBootstrap;
use AISEOAutopilot\SEO\Audit\SiteAuditor;
use AISEOAutopilot\SEO\Breadcrumbs\BreadcrumbsGenerator;
use AISEOAutopilot\SEO\Metadata\MetaboxManager;
use AISEOAutopilot\SEO\Metadata\MetaOutput;
use AISEOAutopilot\SEO\Metadata\MetaRepository;
use AISEOAutopilot\Schema\SchemaGenerator;
use AISEOAutopilot\Sitemap\RobotsTxt;
use AISEOAutopilot\Sitemap\SitemapGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	/** @var array<string,object> Simple service container. */
	private array $services = array();

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Boot the plugin. Idempotent.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// No load_plugin_textdomain() call: WordPress.org auto-loads
		// translations by plugin slug since WP 4.6, and a manual call this
		// early (during plugins_loaded itself) triggers a
		// _load_textdomain_just_in_time notice on WP 6.7+.

		$this->maybe_upgrade();
		$this->register_services();

		/**
		 * Fires once AI SEO Autopilot (Free) core services are registered.
		 * Pro uses this to detect Free and register its own services.
		 *
		 * @param Plugin $plugin
		 */
		do_action( 'ai_seo_autopilot/loaded', $this );
	}

	/**
	 * Run DB migrations if the stored DB version differs from the code version.
	 * Handles upgrades that happen without a fresh activation (e.g. plugin update).
	 */
	private function maybe_upgrade(): void {
		$installed = get_option( 'ai_seo_autopilot_db_version', '' );

		if ( $installed !== AI_SEO_AUTOPILOT_DB_VERSION ) {
			Migrator::install();
			update_option( 'ai_seo_autopilot_db_version', AI_SEO_AUTOPILOT_DB_VERSION );
		}
	}

	private function register_services(): void {
		// Cross-cutting: feature locking, capabilities, upgrade messaging.
		$this->set( 'features', new FeatureManager() );
		$this->set( 'capabilities', new CapabilityManager() );
		$this->set( 'upgrade', new UpgradeManager() );

		// AI subsystem.
		$this->set( 'ai.usage', new UsageTracker() );
		$this->set( 'ai.prompts', new PromptManager() );
		$this->set( 'ai.providers', new AIProviderManager( $this->get( 'features' ) ) );

		// SEO data layer.
		$this->set( 'seo.meta_repository', new MetaRepository() );
		$this->set( 'seo.meta_output', new MetaOutput( $this->get( 'seo.meta_repository' ) ) );
		$this->set( 'seo.metabox', new MetaboxManager( $this->get( 'seo.meta_repository' ) ) );
		$this->set( 'seo.breadcrumbs', new BreadcrumbsGenerator() );
		$this->set( 'seo.analyzer', new SeoAnalyzer() );
		$this->set( 'seo.auditor', new SiteAuditor( $this->get( 'seo.meta_repository' ) ) );

		$this->set( 'schema.generator', new SchemaGenerator( $this->get( 'seo.meta_repository' ) ) );
		$this->set( 'sitemap.generator', new SitemapGenerator() );
		$this->set( 'sitemap.robots', new RobotsTxt() );

		$this->set( 'compat.seo_plugins', new SeoPluginDetector() );
		$this->set( 'compat.pro_bridge', new ProBridge() );

		$this->set( 'cron.scheduler', new Scheduler() );

		if ( is_admin() ) {
			$this->set( 'admin.settings', new Settings( $this->get( 'features' ) ) );
			$this->set( 'admin.menu', new Menu( $this->get( 'features' ) ) );
			$this->set( 'admin.assets', new Assets() );
		}

		$this->set( 'rest', new RestBootstrap( $this ) );

		foreach ( $this->services as $service ) {
			if ( $service instanceof Registrable ) {
				$service->register();
			}
		}
	}

	private function set( string $id, object $service ): void {
		$this->services[ $id ] = $service;
	}

	/**
	 * Fetch a registered service by id. Used internally and by Pro via
	 * ai_seo_autopilot()->get( 'service.id' ).
	 */
	public function get( string $id ): ?object {
		return $this->services[ $id ] ?? null;
	}

	public function has( string $id ): bool {
		return isset( $this->services[ $id ] );
	}
}
