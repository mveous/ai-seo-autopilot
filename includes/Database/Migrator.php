<?php
/**
 * Creates / upgrades custom database tables via dbDelta.
 *
 * @package AISEOAutopilot\Database
 */

namespace AISEOAutopilot\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Migrator {

	/**
	 * Return fully-qualified table name for a given short name.
	 * Short names: scans, issues, actions, activity, index, keywords, redirects, ai_usage.
	 */
	public static function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . 'ai_seo_' . $name;
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		foreach ( self::schemas( $charset_collate ) as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * @return string[] CREATE TABLE statements, dbDelta-formatted.
	 */
	private static function schemas( string $charset_collate ): array {
		$scans     = self::table( 'scans' );
		$issues    = self::table( 'issues' );
		$actions   = self::table( 'actions' );
		$activity  = self::table( 'activity' );
		$index     = self::table( 'index' );
		$keywords  = self::table( 'keywords' );
		$redirects = self::table( 'redirects' );
		$ai_usage  = self::table( 'ai_usage' );

		return array(
			"CREATE TABLE {$scans} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				scan_type VARCHAR(50) NOT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'queued',
				total_items INT UNSIGNED NOT NULL DEFAULT 0,
				processed_items INT UNSIGNED NOT NULL DEFAULT 0,
				results_summary LONGTEXT NULL,
				triggered_by VARCHAR(20) NOT NULL DEFAULT 'user',
				started_at DATETIME NULL,
				finished_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY scan_type (scan_type),
				KEY status (status),
				KEY created_at (created_at)
			) {$charset_collate};",

			"CREATE TABLE {$issues} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				scan_id BIGINT UNSIGNED NULL,
				object_type VARCHAR(30) NOT NULL,
				object_id BIGINT UNSIGNED NULL,
				issue_type VARCHAR(60) NOT NULL,
				category VARCHAR(30) NOT NULL DEFAULT 'technical',
				severity VARCHAR(10) NOT NULL DEFAULT 'medium',
				title VARCHAR(255) NOT NULL,
				description TEXT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'open',
				meta LONGTEXT NULL,
				detected_at DATETIME NOT NULL,
				resolved_at DATETIME NULL,
				PRIMARY KEY  (id),
				KEY scan_id (scan_id),
				KEY object (object_type, object_id),
				KEY issue_type (issue_type),
				KEY severity (severity),
				KEY status (status)
			) {$charset_collate};",

			"CREATE TABLE {$actions} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				issue_id BIGINT UNSIGNED NULL,
				action_type VARCHAR(60) NOT NULL,
				object_type VARCHAR(30) NOT NULL,
				object_id BIGINT UNSIGNED NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'pending_approval',
				risk_level VARCHAR(10) NOT NULL DEFAULT 'safe',
				payload LONGTEXT NULL,
				previous_value LONGTEXT NULL,
				created_by BIGINT UNSIGNED NULL,
				applied_by BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				applied_at DATETIME NULL,
				PRIMARY KEY  (id),
				KEY issue_id (issue_id),
				KEY object (object_type, object_id),
				KEY status (status),
				KEY action_type (action_type)
			) {$charset_collate};",

			"CREATE TABLE {$activity} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				action_id BIGINT UNSIGNED NULL,
				user_id BIGINT UNSIGNED NULL,
				event_type VARCHAR(60) NOT NULL,
				object_type VARCHAR(30) NULL,
				object_id BIGINT UNSIGNED NULL,
				description TEXT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY action_id (action_id),
				KEY object (object_type, object_id),
				KEY event_type (event_type),
				KEY created_at (created_at)
			) {$charset_collate};",

			"CREATE TABLE {$index} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				object_type VARCHAR(30) NOT NULL,
				object_id BIGINT UNSIGNED NOT NULL,
				title VARCHAR(255) NULL,
				content_hash VARCHAR(64) NULL,
				description_hash VARCHAR(64) NULL,
				word_count INT UNSIGNED NOT NULL DEFAULT 0,
				topics LONGTEXT NULL,
				keywords LONGTEXT NULL,
				internal_links_out LONGTEXT NULL,
				seo_score TINYINT UNSIGNED NULL,
				score_breakdown LONGTEXT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY object (object_type, object_id),
				KEY content_hash (content_hash),
				KEY description_hash (description_hash)
			) {$charset_collate};",

			"CREATE TABLE {$keywords} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				object_type VARCHAR(30) NOT NULL,
				object_id BIGINT UNSIGNED NULL,
				keyword VARCHAR(191) NOT NULL,
				source VARCHAR(20) NOT NULL DEFAULT 'manual',
				search_volume INT UNSIGNED NULL,
				position DECIMAL(5,2) NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY object (object_type, object_id),
				KEY keyword (keyword)
			) {$charset_collate};",

			"CREATE TABLE {$redirects} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				source_path VARCHAR(500) NOT NULL,
				target_path VARCHAR(500) NOT NULL,
				redirect_type SMALLINT UNSIGNED NOT NULL DEFAULT 301,
				status VARCHAR(10) NOT NULL DEFAULT 'active',
				hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
				created_by BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				last_hit_at DATETIME NULL,
				PRIMARY KEY  (id),
				KEY source_hash (source_path(191)),
				KEY status (status)
			) {$charset_collate};",

			"CREATE TABLE {$ai_usage} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				provider VARCHAR(30) NOT NULL,
				model VARCHAR(100) NOT NULL,
				feature VARCHAR(60) NOT NULL,
				object_type VARCHAR(30) NULL,
				object_id BIGINT UNSIGNED NULL,
				prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
				completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
				total_tokens INT UNSIGNED NOT NULL DEFAULT 0,
				estimated_cost DECIMAL(10,5) NOT NULL DEFAULT 0,
				status VARCHAR(10) NOT NULL DEFAULT 'success',
				user_id BIGINT UNSIGNED NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY provider (provider),
				KEY feature (feature),
				KEY created_at (created_at)
			) {$charset_collate};",
		);
	}
}
