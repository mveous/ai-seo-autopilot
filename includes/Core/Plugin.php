<?php
/**
 * Main plugin orchestrator (singleton).
 *
 * @package Aasa\Core
 */

namespace Aasa\Core;

use Aasa\AI\AIClient;
use Aasa\AI\AIProviderManager;
use Aasa\AI\PromptManager;
use Aasa\AI\UsageTracker;
use Aasa\Admin\Assets;
use Aasa\Admin\Menu;
use Aasa\Admin\Settings;
use Aasa\Compatibility\SeoPluginDetector;
use Aasa\Content\Analyzer\SeoAnalyzer;
use Aasa\Cron\Scheduler;
use Aasa\Database\Migrator;
use Aasa\Features\CapabilityManager;
use Aasa\REST\RestBootstrap;
use Aasa\SEO\Audit\SiteAuditor;
use Aasa\SEO\Breadcrumbs\BreadcrumbsGenerator;
use Aasa\SEO\Metadata\MetaboxManager;
use Aasa\SEO\Metadata\MetaOutput;
use Aasa\SEO\Metadata\MetaRepository;
use Aasa\Schema\SchemaGenerator;
use Aasa\Sitemap\RobotsTxt;
use Aasa\Sitemap\SitemapGenerator;

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
		 * Fires once AI Autopilot SEO Assistant's core services are registered.
		 * Third-party extensions use this to register their own services.
		 *
		 * @param Plugin $plugin
		 */
		do_action( 'aasa/loaded', $this );
	}

	/**
	 * Run DB migrations if the stored DB version differs from the code version.
	 * Handles upgrades that happen without a fresh activation (e.g. plugin update).
	 */
	private function maybe_upgrade(): void {
		$installed = get_option( 'aasa_db_version', '' );

		if ( $installed !== AASA_DB_VERSION ) {
			Migrator::install();
			update_option( 'aasa_db_version', AASA_DB_VERSION );
		}
	}

	private function register_services(): void {
		// Cross-cutting: capabilities.
		$this->set( 'capabilities', new CapabilityManager() );

		// AI subsystem.
		AIClient::init();
		$this->set( 'ai.usage', new UsageTracker() );
		$this->set( 'ai.prompts', new PromptManager() );
		$this->set( 'ai.providers', new AIProviderManager() );

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

		$this->set( 'cron.scheduler', new Scheduler() );

		if ( is_admin() ) {
			$this->set( 'admin.settings', new Settings() );
			$this->set( 'admin.menu', new Menu() );
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
	 * Fetch a registered service by id. Used internally and by third-party
	 * extensions via aasa()->get( 'service.id' ).
	 */
	public function get( string $id ): ?object {
		return $this->services[ $id ] ?? null;
	}

	public function has( string $id ): bool {
		return isset( $this->services[ $id ] );
	}
}
