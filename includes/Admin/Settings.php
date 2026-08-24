<?php
/**
 * Settings storage, defaults, and the admin-post handlers that save each
 * settings tab. Rendering lives in admin/views/settings.php; this class
 * only owns data (read, sanitize, persist).
 *
 * @package AISEOAutopilot\Admin
 */

namespace AISEOAutopilot\Admin;

use AISEOAutopilot\AI\AIProviderManager;
use AISEOAutopilot\Core\Plugin;
use AISEOAutopilot\Core\Registrable;
use AISEOAutopilot\Features\CapabilityManager;
use AISEOAutopilot\Features\FeatureManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings implements Registrable {

	public const OPTION = 'ai_seo_autopilot_settings';

	private FeatureManager $features;

	public function __construct( FeatureManager $features ) {
		$this->features = $features;
	}

	public function register(): void {
		add_action( 'admin_post_ai_seo_autopilot_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_ai_seo_autopilot_save_api_key', array( $this, 'handle_save_api_key' ) );
		add_action( 'admin_post_ai_seo_autopilot_delete_api_key', array( $this, 'handle_delete_api_key' ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'general' => array(
				'site_type'                  => 'business',
				'disable_seo_plugin_notices' => false,
			),
			'seo'     => array(
				'title_separator'     => '-',
				'noindex_paginated'   => true,
				'noindex_archives'    => false,
				'strip_category_base' => false,
			),
			'social'  => array(
				'default_og_image' => '',
				'facebook_app_id'  => '',
				'twitter_username' => '',
			),
			'schema'  => array(
				'organization_name' => get_bloginfo( 'name' ),
				'organization_type' => 'Organization',
				'organization_logo' => '',
			),
			'sitemap' => array(
				'enabled'             => true,
				'exclude_post_types'  => array(),
			),
			'ai'      => array(
				'active_provider'       => '',
				'monthly_request_limit' => 0,
			),
			'content' => array(
				'focus_keyword_enabled' => true,
			),
			'advanced' => array(
				'remove_data_on_uninstall' => false,
			),
			'compatibility' => array(
				'control_titles'    => true,
				'control_meta'      => true,
				'control_canonical' => true,
				'control_opengraph' => true,
				'control_schema'    => true,
				'control_sitemap'   => true,
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_replace_recursive( self::defaults(), $stored );
	}

	public function get( string $section, string $key, $default = null ) {
		$all = $this->get_all();

		return $all[ $section ][ $key ] ?? $default;
	}

	// -------------------------------------------------------------
	// Save handlers.
	// -------------------------------------------------------------

	public function handle_save_settings(): void {
		$this->guard( 'manage_settings' );

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard() above.

		$sanitizers = array(
			'general' => array( $this, 'sanitize_general' ),
			'seo'     => array( $this, 'sanitize_seo' ),
			'content' => array( $this, 'sanitize_content' ),
			'schema'  => array( $this, 'sanitize_schema' ),
			'sitemap' => array( $this, 'sanitize_sitemap' ),
			'social'  => array( $this, 'sanitize_social' ),
			'ai'      => array( $this, 'sanitize_ai' ),
			'advanced' => array( $this, 'sanitize_advanced' ),
			'compatibility' => array( $this, 'sanitize_compatibility' ),
		);

		if ( ! isset( $sanitizers[ $tab ] ) ) {
			$this->redirect_back( $tab, 'invalid_tab' );
		}

		$raw     = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in guard() above; $raw is sanitized two lines below via the per-tab sanitizer callback, which whitelists every field.
		$clean   = call_user_func( $sanitizers[ $tab ], $raw );
		$all     = $this->get_all();
		$all[ $tab ] = array_replace( $all[ $tab ] ?? array(), $clean );

		update_option( self::OPTION, $all );

		$this->redirect_back( $tab, 'saved' );
	}

	public function handle_save_api_key(): void {
		$this->guard( 'manage_settings' );

		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard() above.
		$api_key  = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard() above.

		/** @var AIProviderManager|null $manager */
		$manager = Plugin::instance()->get( 'ai.providers' );
		$result  = $manager ? $manager->save_api_key( $provider, $api_key ) : new \WP_Error( 'unavailable', 'AI provider manager unavailable.' );

		if ( is_wp_error( $result ) ) {
			$this->redirect_back( 'ai', 'error', $result->get_error_message() );
		}

		$model = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard() above.
		if ( '' !== $model ) {
			$all               = $this->get_all();
			$all['ai']['active_provider'] = $provider;
			$all['ai']['model'] = $model;
			update_option( self::OPTION, $all );
		} else {
			$all                           = $this->get_all();
			$all['ai']['active_provider']  = $provider;
			update_option( self::OPTION, $all );
		}

		$this->redirect_back( 'ai', 'saved' );
	}

	public function handle_delete_api_key(): void {
		$this->guard( 'manage_settings' );

		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in guard() above.

		/** @var AIProviderManager|null $manager */
		$manager = Plugin::instance()->get( 'ai.providers' );
		$manager?->delete_api_key( $provider );

		$this->redirect_back( 'ai', 'deleted' );
	}

	private function guard( string $action ): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ai_seo_autopilot_settings' ) ) {
			wp_die( esc_html__( 'Security check failed. Please reload the page and try again.', 'ai-seo-autopilot' ), 403 );
		}

		/** @var CapabilityManager|null $capabilities */
		$capabilities = Plugin::instance()->get( 'capabilities' );

		if ( ! $capabilities || ! $capabilities->current_user_can( $action ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'ai-seo-autopilot' ), 403 );
		}
	}

	private function redirect_back( string $tab, string $status, string $message = '' ): void {
		$url = add_query_arg(
			array_filter(
				array(
					'page'    => 'ai-seo-autopilot-settings',
					'tab'     => $tab,
					'status'  => $status,
					'message' => $message ? rawurlencode( $message ) : '',
				)
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	// -------------------------------------------------------------
	// Sanitizers.
	// -------------------------------------------------------------

	private function sanitize_general( array $raw ): array {
		$allowed_types = array( 'blog', 'business', 'ecommerce', 'portfolio', 'news', 'local' );
		$type          = $raw['site_type'] ?? 'business';

		return array(
			'site_type'                  => in_array( $type, $allowed_types, true ) ? $type : 'business',
			'disable_seo_plugin_notices' => ! empty( $raw['disable_seo_plugin_notices'] ),
		);
	}

	private function sanitize_seo( array $raw ): array {
		$separators = array( '-', '|', '·', '•', '—' );
		$sep        = $raw['title_separator'] ?? '-';

		return array(
			'title_separator'     => in_array( $sep, $separators, true ) ? $sep : '-',
			'noindex_paginated'   => ! empty( $raw['noindex_paginated'] ),
			'noindex_archives'    => ! empty( $raw['noindex_archives'] ),
			'strip_category_base' => ! empty( $raw['strip_category_base'] ),
		);
	}

	private function sanitize_content( array $raw ): array {
		return array(
			'focus_keyword_enabled' => ! empty( $raw['focus_keyword_enabled'] ),
		);
	}

	private function sanitize_schema( array $raw ): array {
		$allowed_org_types = array( 'Organization', 'LocalBusiness', 'Corporation', 'EducationalOrganization', 'NGO' );
		$org_type          = $raw['organization_type'] ?? 'Organization';

		return array(
			'organization_name' => isset( $raw['organization_name'] ) ? sanitize_text_field( $raw['organization_name'] ) : '',
			'organization_type' => in_array( $org_type, $allowed_org_types, true ) ? $org_type : 'Organization',
			'organization_logo' => isset( $raw['organization_logo'] ) ? esc_url_raw( $raw['organization_logo'] ) : '',
		);
	}

	private function sanitize_sitemap( array $raw ): array {
		$excluded = array();
		if ( ! empty( $raw['exclude_post_types'] ) && is_array( $raw['exclude_post_types'] ) ) {
			$excluded = array_map( 'sanitize_key', $raw['exclude_post_types'] );
		}

		return array(
			'enabled'            => ! empty( $raw['enabled'] ),
			'exclude_post_types' => $excluded,
		);
	}

	private function sanitize_social( array $raw ): array {
		return array(
			'default_og_image' => isset( $raw['default_og_image'] ) ? esc_url_raw( $raw['default_og_image'] ) : '',
			'facebook_app_id'  => isset( $raw['facebook_app_id'] ) ? sanitize_text_field( $raw['facebook_app_id'] ) : '',
			'twitter_username' => isset( $raw['twitter_username'] ) ? sanitize_text_field( ltrim( $raw['twitter_username'], '@' ) ) : '',
		);
	}

	private function sanitize_ai( array $raw ): array {
		return array(
			'monthly_request_limit' => isset( $raw['monthly_request_limit'] ) ? max( 0, absint( $raw['monthly_request_limit'] ) ) : 0,
		);
	}

	private function sanitize_advanced( array $raw ): array {
		return array(
			'remove_data_on_uninstall' => ! empty( $raw['remove_data_on_uninstall'] ),
		);
	}

	private function sanitize_compatibility( array $raw ): array {
		$clean = array();
		foreach ( array( 'titles', 'meta', 'canonical', 'opengraph', 'schema', 'sitemap' ) as $area ) {
			$clean[ 'control_' . $area ] = ! empty( $raw[ 'control_' . $area ] );
		}

		return $clean;
	}
}
