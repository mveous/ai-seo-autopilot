<?php
/**
 * Centralized feature-capability registry.
 *
 * Every Free and Pro feature is registered here exactly once, with
 * metadata used to render either the live feature or a locked upsell
 * card. Nothing else in the codebase should hardcode `if ( ! pro )`
 * checks — it should call FeatureManager::is_available( $key ) instead.
 *
 * @package AISEOAutopilot\Features
 */

namespace AISEOAutopilot\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FeatureManager {

	/** @var array<string,array<string,mixed>> */
	private array $features = array();

	public function __construct() {
		$this->register_core_features();

		/**
		 * Allows Pro (or third parties) to register additional features
		 * into the same locked/unlocked card system.
		 *
		 * @param FeatureManager $manager
		 */
		do_action( 'ai_seo_autopilot/register_features', $this );
	}

	/**
	 * Register or overwrite a feature definition.
	 *
	 * @param string $key  Unique feature key, e.g. 'seo_autopilot'.
	 * @param array{
	 *     name: string,
	 *     description: string,
	 *     benefits?: string[],
	 *     category?: string,
	 *     pro: bool,
	 *     icon?: string
	 * } $args
	 */
	public function register( string $key, array $args ): void {
		$this->features[ $key ] = wp_parse_args(
			$args,
			array(
				'name'        => $key,
				'description' => '',
				'benefits'    => array(),
				'category'    => 'general',
				'pro'         => true,
				'icon'        => 'dashicons-star-filled',
			)
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get( string $key ): ?array {
		return $this->features[ $key ] ?? null;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function all(): array {
		return $this->features;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function in_category( string $category ): array {
		return array_filter(
			$this->features,
			static fn( array $feature ) => $feature['category'] === $category
		);
	}

	/**
	 * Whether a registered feature can actually run on this site right now.
	 */
	public function is_available( string $key ): bool {
		$feature = $this->get( $key );

		if ( null === $feature ) {
			// Unknown features fail closed.
			return false;
		}

		if ( empty( $feature['pro'] ) ) {
			return true;
		}

		return $this->pro_active();
	}

	/**
	 * Whether a valid, licensed Pro installation is active. Pro adds this
	 * filter once its license is verified; Free always defaults to false.
	 */
	public function pro_active(): bool {
		return (bool) apply_filters( 'ai_seo_autopilot/pro_active', false );
	}

	private function register_core_features(): void {
		// ---- Free features (always available) -----------------------
		$this->register(
			'seo_metadata',
			array(
				'name'        => __( 'SEO Titles & Meta', 'ai-seo-autopilot' ),
				'description' => __( 'Titles, meta descriptions, canonical URLs, robots meta.', 'ai-seo-autopilot' ),
				'category'    => 'seo',
				'pro'         => false,
			)
		);
		$this->register(
			'sitemap',
			array(
				'name'        => __( 'XML Sitemap', 'ai-seo-autopilot' ),
				'description' => __( 'Automatic XML sitemap generation.', 'ai-seo-autopilot' ),
				'category'    => 'seo',
				'pro'         => false,
			)
		);
		$this->register(
			'basic_schema',
			array(
				'name'        => __( 'Basic Schema Markup', 'ai-seo-autopilot' ),
				'description' => __( 'Article, WebPage, Organization and Breadcrumb JSON-LD.', 'ai-seo-autopilot' ),
				'category'    => 'schema',
				'pro'         => false,
			)
		);
		$this->register(
			'ai_generation',
			array(
				'name'        => __( 'AI Content Generation', 'ai-seo-autopilot' ),
				'description' => __( 'AI-generated titles, meta descriptions, ALT text and keyword suggestions using your own API key.', 'ai-seo-autopilot' ),
				'category'    => 'ai',
				'pro'         => false,
			)
		);
		$this->register(
			'site_audit',
			array(
				'name'        => __( 'SEO Site Audit', 'ai-seo-autopilot' ),
				'description' => __( 'Full-site scan for missing metadata, duplicate content, broken links and thin content.', 'ai-seo-autopilot' ),
				'category'    => 'seo',
				'pro'         => false,
			)
		);

		// ---- Pro features (locked by default) ------------------------
		$this->register(
			'ai_multi_provider',
			array(
				'name'        => __( 'Bring Your Own AI Provider', 'ai-seo-autopilot' ),
				'description' => __( 'Free uses Anthropic Claude for AI generation. Pro lets you connect OpenAI, Google Gemini, or Claude — any provider, any model.', 'ai-seo-autopilot' ),
				'benefits'    => array(
					__( 'Use OpenAI (GPT) or Google Gemini instead of Claude', 'ai-seo-autopilot' ),
					__( 'Switch providers per feature', 'ai-seo-autopilot' ),
					__( 'Pick the model that fits your budget and quality needs', 'ai-seo-autopilot' ),
				),
				'category'    => 'ai',
				'pro'         => true,
				'icon'        => 'dashicons-admin-generic',
			)
		);
		$this->register(
			'seo_autopilot',
			array(
				'name'        => __( 'AI SEO Autopilot', 'ai-seo-autopilot' ),
				'description' => __( 'Automatically analyze and fix SEO issues across your website.', 'ai-seo-autopilot' ),
				'benefits'    => array(
					__( 'AI prioritizes every SEO issue on your site', 'ai-seo-autopilot' ),
					__( 'Safe issues are fixed automatically', 'ai-seo-autopilot' ),
					__( 'Risky changes wait for your approval', 'ai-seo-autopilot' ),
					__( 'Every change is validated and logged', 'ai-seo-autopilot' ),
				),
				'category'    => 'automation',
				'pro'         => true,
				'icon'        => 'dashicons-superhero',
			)
		);
		$this->register(
			'search_console',
			array(
				'name'        => __( 'Google Search Console', 'ai-seo-autopilot' ),
				'description' => __( 'Connect Search Console and let AI discover keyword opportunities, CTR problems and ranking drops.', 'ai-seo-autopilot' ),
				'benefits'    => array(
					__( 'Clicks, impressions, CTR and average position', 'ai-seo-autopilot' ),
					__( 'AI-written recommendations per page', 'ai-seo-autopilot' ),
					__( 'One-click apply for title/meta fixes', 'ai-seo-autopilot' ),
				),
				'category'    => 'reports',
				'pro'         => true,
				'icon'        => 'dashicons-chart-line',
			)
		);
		$this->register(
			'opportunity_engine',
			array(
				'name'        => __( 'SEO Opportunity Engine', 'ai-seo-autopilot' ),
				'description' => __( 'Finds keyword, content and internal-linking opportunities and prioritizes them by impact.', 'ai-seo-autopilot' ),
				'category'    => 'reports',
				'pro'         => true,
				'icon'        => 'dashicons-lightbulb',
			)
		);
		$this->register(
			'internal_linking',
			array(
				'name'        => __( 'Internal Linking Automation', 'ai-seo-autopilot' ),
				'description' => __( 'Finds orphan pages and weak internal links, and suggests (or automatically inserts) contextual links.', 'ai-seo-autopilot' ),
				'category'    => 'automation',
				'pro'         => true,
				'icon'        => 'dashicons-admin-links',
			)
		);
		$this->register(
			'advanced_schema',
			array(
				'name'        => __( 'Advanced Schema Markup', 'ai-seo-autopilot' ),
				'description' => __( 'AI-detected schema for Product, Service, LocalBusiness, Event, Recipe, FAQ, HowTo and more.', 'ai-seo-autopilot' ),
				'category'    => 'schema',
				'pro'         => true,
				'icon'        => 'dashicons-editor-code',
			)
		);
		$this->register(
			'technical_seo',
			array(
				'name'        => __( 'Technical SEO Automation', 'ai-seo-autopilot' ),
				'description' => __( '404 monitoring, redirect management, duplicate/thin/orphan content detection.', 'ai-seo-autopilot' ),
				'category'    => 'technical',
				'pro'         => true,
				'icon'        => 'dashicons-admin-tools',
			)
		);
		$this->register(
			'content_decay',
			array(
				'name'        => __( 'Content Decay Monitoring', 'ai-seo-autopilot' ),
				'description' => __( 'Detects ranking, traffic and CTR drops and explains why a page may be losing visibility.', 'ai-seo-autopilot' ),
				'category'    => 'reports',
				'pro'         => true,
				'icon'        => 'dashicons-chart-area',
			)
		);
		$this->register(
			'competitor_analysis',
			array(
				'name'        => __( 'Competitor Analysis', 'ai-seo-autopilot' ),
				'description' => __( 'Identifies topic, keyword and content-structure gaps against competitor pages.', 'ai-seo-autopilot' ),
				'category'    => 'reports',
				'pro'         => true,
				'icon'        => 'dashicons-groups',
			)
		);
		$this->register(
			'woocommerce_seo',
			array(
				'name'        => __( 'WooCommerce SEO', 'ai-seo-autopilot' ),
				'description' => __( 'Product SEO titles, descriptions, schema and structured data.', 'ai-seo-autopilot' ),
				'category'    => 'woocommerce',
				'pro'         => true,
				'icon'        => 'dashicons-cart',
			)
		);
		$this->register(
			'local_seo',
			array(
				'name'        => __( 'Local SEO', 'ai-seo-autopilot' ),
				'description' => __( 'LocalBusiness schema, service areas, opening hours and local landing pages.', 'ai-seo-autopilot' ),
				'category'    => 'local',
				'pro'         => true,
				'icon'        => 'dashicons-location',
			)
		);
		$this->register(
			'multilingual_seo',
			array(
				'name'        => __( 'Multilingual SEO', 'ai-seo-autopilot' ),
				'description' => __( 'hreflang, localized metadata and duplicate-translation detection.', 'ai-seo-autopilot' ),
				'category'    => 'multilingual',
				'pro'         => true,
				'icon'        => 'dashicons-translation',
			)
		);
		$this->register(
			'agency_reports',
			array(
				'name'        => __( 'Advanced Reports & Agency Tools', 'ai-seo-autopilot' ),
				'description' => __( 'Client-ready SEO reports and multi-site management.', 'ai-seo-autopilot' ),
				'category'    => 'reports',
				'pro'         => true,
				'icon'        => 'dashicons-portfolio',
			)
		);
	}
}
