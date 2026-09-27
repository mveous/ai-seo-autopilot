<?php
/**
 * Full-site SEO scanner. Runs in small batches (never a single expensive
 * synchronous pass over the whole site) so it's safe to call from a REST
 * request (one batch, for instant dashboard feedback) or from WP-Cron
 * (repeated batches until the queue drains).
 *
 * @package AISEOAutopilot\SEO\Audit
 */

namespace AISEOAutopilot\SEO\Audit;

use AISEOAutopilot\Admin\Settings;
use AISEOAutopilot\Content\Analyzer\ContentScanner;
use AISEOAutopilot\Content\Analyzer\SeoAnalyzer;
use AISEOAutopilot\Database\Migrator;
use AISEOAutopilot\SEO\Metadata\MetaRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteAuditor {

	private const QUEUE_OPTION       = 'ai_seo_autopilot_scan_queue';
	private const ACCUMULATOR_OPTION = 'ai_seo_autopilot_scan_accumulator';
	private const CURRENT_SCAN_OPTION = 'ai_seo_autopilot_current_scan_id';
	private const SUMMARY_OPTION     = 'ai_seo_autopilot_last_scan_summary';

	private const CATEGORY_WEIGHTS = array(
		'technical' => 20,
		'on_page'   => 25,
		'content'   => 25,
		'schema'    => 15,
		'images'    => 15,
	);

	private MetaRepository $repository;

	private SeoAnalyzer $analyzer;

	private ContentScanner $scanner;

	public function __construct( MetaRepository $repository ) {
		$this->repository = $repository;
		$this->analyzer   = new SeoAnalyzer();
		$this->scanner    = new ContentScanner();
	}

	public function is_scan_in_progress(): bool {
		return ! empty( get_option( self::QUEUE_OPTION, array() ) );
	}

	/**
	 * Process the next batch of the current scan, starting a new scan if
	 * none is in progress.
	 *
	 * @return array{processed:int,remaining:int,finished:bool}
	 */
	public function run_batch( int $batch_size = 50 ): array {
		if ( ! $this->is_scan_in_progress() ) {
			$this->start_new_scan();
		}

		$queue = get_option( self::QUEUE_OPTION, array() );
		$batch = array_splice( $queue, 0, $batch_size );

		foreach ( $batch as $post_id ) {
			$this->process_post( (int) $post_id );
		}

		update_option( self::QUEUE_OPTION, $queue, false );
		$this->bump_processed_count( count( $batch ) );

		$finished = empty( $queue );
		if ( $finished ) {
			$this->finalize_scan();
		}

		return array(
			'processed' => count( $batch ),
			'remaining' => count( $queue ),
			'finished'  => $finished,
		);
	}

	private function start_new_scan(): void {
		global $wpdb;

		$settings   = get_option( Settings::OPTION, array() );
		$excluded   = (array) ( $settings['sitemap']['exclude_post_types'] ?? array() );
		$post_types = array_diff( get_post_types( array( 'public' => true ), 'names' ), $excluded );
		unset( $post_types['attachment'] );

		$ids = get_posts(
			array(
				'post_type'      => array_values( $post_types ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		update_option( self::QUEUE_OPTION, $ids, false );
		update_option(
			self::ACCUMULATOR_OPTION,
			array(
				'count'                => 0,
				'sum_title'            => 0,
				'sum_description'      => 0,
				'sum_keyword'          => 0,
				'sum_headings'         => 0,
				'sum_content'          => 0,
				'sum_images'           => 0,
				'sum_links'            => 0,
				'sum_readability'      => 0,
				'posts_with_broken_links' => 0,
			),
			false
		);

		$table = Migrator::table( 'scans' );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $table is from Migrator::table(), custom plugin table, no core API exists.
			$table,
			array(
				'scan_type'    => 'full_site',
				'status'       => 'running',
				'total_items'  => count( $ids ),
				'processed_items' => 0,
				'triggered_by' => is_admin() && get_current_user_id() ? 'user' : 'cron',
				'started_at'   => current_time( 'mysql', true ),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		update_option( self::CURRENT_SCAN_OPTION, (int) $wpdb->insert_id, false );
	}

	private function bump_processed_count( int $delta ): void {
		global $wpdb;

		$scan_id = (int) get_option( self::CURRENT_SCAN_OPTION, 0 );
		if ( ! $scan_id ) {
			return;
		}

		$table = Migrator::table( 'scans' );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is from Migrator::table(), custom plugin table, no core API exists; caching is a possible future optimization.
			$wpdb->prepare(
				"UPDATE {$table} SET processed_items = processed_items + %d WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$delta,
				$scan_id
			)
		);
	}

	private function process_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$analysis  = $this->analyzer->analyze_post( $post_id );
		$html      = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- invoking WordPress core's own filter, not defining a new hook.
		$structure = $this->scanner->analyze( $html );

		$title       = (string) $this->repository->get( $post_id, 'title' );
		$title       = '' !== $title ? $title : get_the_title( $post );
		$description = (string) $this->repository->get( $post_id, 'description' );
		if ( '' === $description ) {
			$source      = '' !== $post->post_excerpt ? $post->post_excerpt : $post->post_content;
			$description = wp_trim_words( wp_strip_all_tags( $source ), 30, '…' );
		}

		$this->upsert_index_row( $post, $title, $description, $structure, $analysis );
		$this->write_issues( $post, $title, $description, $structure, $analysis );
		$this->accumulate( $analysis, $structure );
	}

	/**
	 * @param array<string,mixed> $structure
	 * @param array<string,mixed> $analysis
	 */
	private function upsert_index_row( \WP_Post $post, string $title, string $description, array $structure, array $analysis ): void {
		global $wpdb;

		$table = Migrator::table( 'index' );

		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $table is from Migrator::table(), custom plugin table, no core API exists; caching is a possible future optimization.
			$table,
			array(
				'object_type'        => $post->post_type,
				'object_id'          => $post->ID,
				'title'              => wp_strip_all_tags( $title ),
				'content_hash'       => md5( wp_strip_all_tags( $post->post_content ) ),
				'description_hash'   => '' !== $description ? md5( strtolower( trim( $description ) ) ) : null,
				'word_count'         => $structure['word_count'],
				'keywords'           => wp_json_encode( array_filter( array( (string) $this->repository->get( $post->ID, 'focus_keyword' ) ) ) ),
				'internal_links_out' => wp_json_encode( wp_list_pluck( array_filter( $structure['links'], static fn( $l ) => $l['internal'] ), 'href' ) ),
				'seo_score'          => $analysis['score'],
				'score_breakdown'    => wp_json_encode( $analysis['breakdown'] ),
				'updated_at'         => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * @param array<string,mixed> $structure
	 * @param array<string,mixed> $analysis
	 */
	private function write_issues( \WP_Post $post, string $title, string $description, array $structure, array $analysis ): void {
		$this->delete_issues_for_object( $post->post_type, $post->ID );

		$issues = array();

		if ( '' === trim( $title ) ) {
			$issues[] = $this->issue( $post, 'missing_title', 'on_page', 'high', __( 'Missing SEO title', 'ai-seo-autopilot' ), __( 'This page has no SEO title set and no fallback title could be derived.', 'ai-seo-autopilot' ) );
		}

		if ( '' === trim( $description ) ) {
			$issues[] = $this->issue( $post, 'missing_description', 'on_page', 'medium', __( 'Missing meta description', 'ai-seo-autopilot' ), __( 'This page has no meta description set.', 'ai-seo-autopilot' ) );
		}

		$h1_count = count( array_filter( $structure['headings'], static fn( $h ) => 1 === $h['level'] ) );
		if ( 0 === $h1_count ) {
			$issues[] = $this->issue( $post, 'missing_h1', 'on_page', 'medium', __( 'Missing H1 heading', 'ai-seo-autopilot' ), __( 'No H1 heading was found in the content.', 'ai-seo-autopilot' ) );
		} elseif ( $h1_count > 1 ) {
			$issues[] = $this->issue( $post, 'multiple_h1', 'on_page', 'low', __( 'Multiple H1 headings', 'ai-seo-autopilot' ), sprintf( /* translators: %d: number of H1 headings */ __( '%d H1 headings were found; use only one per page.', 'ai-seo-autopilot' ), $h1_count ) );
		}

		$missing_alt = array_values( array_filter( $structure['images'], static fn( $img ) => ! $img['has_alt'] ) );
		if ( ! empty( $missing_alt ) ) {
			$issues[] = $this->issue(
				$post,
				'missing_alt',
				'images',
				'low',
				__( 'Images missing ALT text', 'ai-seo-autopilot' ),
				sprintf( /* translators: %d: number of images */ __( '%d image(s) are missing ALT text.', 'ai-seo-autopilot' ), count( $missing_alt ) ),
				array( 'images' => array_slice( wp_list_pluck( $missing_alt, 'src' ), 0, 10 ) )
			);
		}

		if ( $structure['word_count'] > 0 && $structure['word_count'] < 300 ) {
			$severity = $structure['word_count'] < 100 ? 'high' : 'medium';
			$issues[] = $this->issue( $post, 'thin_content', 'content', $severity, __( 'Thin content', 'ai-seo-autopilot' ), sprintf( /* translators: %d: word count */ __( 'This page has only %d words.', 'ai-seo-autopilot' ), $structure['word_count'] ) );
		}

		$broken = $this->find_broken_links( $structure['links'] );
		if ( ! empty( $broken ) ) {
			$issues[] = $this->issue(
				$post,
				'broken_internal_link',
				'technical',
				'medium',
				__( 'Broken internal link', 'ai-seo-autopilot' ),
				sprintf( /* translators: %d: number of broken links */ __( '%d internal link(s) appear to point to pages that no longer exist.', 'ai-seo-autopilot' ), count( $broken ) ),
				array( 'links' => array_slice( $broken, 0, 10 ) )
			);
		}

		if ( $this->repository->get( $post->ID, 'robots_noindex' ) ) {
			$issues[] = $this->issue( $post, 'noindex_page', 'technical', 'low', __( 'Page set to noindex', 'ai-seo-autopilot' ), __( 'This page is excluded from search engine indexing.', 'ai-seo-autopilot' ) );
		}

		if ( ! empty( $issues ) ) {
			$this->insert_issues( $issues );
		}
	}

	/**
	 * @param array<int,array{href:string,text:string,internal:bool}> $links
	 *
	 * @return string[]
	 */
	private function find_broken_links( array $links ): array {
		$broken           = array();
		$static_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'zip', 'css', 'js', 'webp' );

		foreach ( $links as $link ) {
			if ( ! $link['internal'] ) {
				continue;
			}

			$path      = wp_parse_url( $link['href'], PHP_URL_PATH ) ?? '';
			$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

			if ( in_array( $extension, $static_extensions, true ) ) {
				continue;
			}

			if ( '' === $path || '/' === $path ) {
				continue;
			}

			if ( 0 === url_to_postid( $link['href'] ) && ! is_object( get_page_by_path( trim( $path, '/' ) ) ) ) {
				$broken[] = $link['href'];
			}
		}

		return array_values( array_unique( $broken ) );
	}

	/**
	 * @param array<string,mixed> $meta
	 *
	 * @return array<string,mixed>
	 */
	private function issue( \WP_Post $post, string $type, string $category, string $severity, string $title, string $description, array $meta = array() ): array {
		return array(
			'object_type' => $post->post_type,
			'object_id'   => $post->ID,
			'issue_type'  => $type,
			'category'    => $category,
			'severity'    => $severity,
			'title'       => $title,
			'description' => $description,
			'status'      => 'open',
			'meta'        => wp_json_encode( $meta ),
			'detected_at' => current_time( 'mysql', true ),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $issues
	 */
	private function insert_issues( array $issues ): void {
		global $wpdb;

		$table = Migrator::table( 'issues' );

		foreach ( $issues as $issue ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $table is from Migrator::table(), custom plugin table, no core API exists.
				$table,
				$issue,
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	private function delete_issues_for_object( string $object_type, int $object_id ): void {
		global $wpdb;

		$table = Migrator::table( 'issues' );

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $table is from Migrator::table(), custom plugin table, no core API exists; caching is a possible future optimization.
			$table,
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
			),
			array( '%s', '%d' )
		);
	}

	/**
	 * @param array<string,mixed> $analysis
	 * @param array<string,mixed> $structure
	 */
	private function accumulate( array $analysis, array $structure ): void {
		$acc = get_option( self::ACCUMULATOR_OPTION, array() );
		if ( empty( $acc ) ) {
			return;
		}

		$breakdown = $analysis['breakdown'];

		$acc['count']           += 1;
		$acc['sum_title']       += $breakdown['title'] ?? 0;
		$acc['sum_description'] += $breakdown['description'] ?? 0;
		$acc['sum_keyword']     += $breakdown['keyword'] ?? 0;
		$acc['sum_headings']    += $breakdown['headings'] ?? 0;
		$acc['sum_content']     += $breakdown['content'] ?? 0;
		$acc['sum_images']      += $breakdown['images'] ?? 0;
		$acc['sum_links']       += $breakdown['links'] ?? 0;
		$acc['sum_readability'] += $breakdown['readability'] ?? 0;

		if ( ! empty( $this->find_broken_links( $structure['links'] ) ) ) {
			$acc['posts_with_broken_links'] += 1;
		}

		update_option( self::ACCUMULATOR_OPTION, $acc, false );
	}

	private function finalize_scan(): void {
		global $wpdb;

		$this->detect_site_wide_duplicates();

		$acc   = get_option( self::ACCUMULATOR_OPTION, array() );
		$count = max( 1, (int) ( $acc['count'] ?? 0 ) );

		$avg = static fn( string $key ) => (int) round( ( $acc[ $key ] ?? 0 ) / $count );

		$on_page = (int) round( ( $avg( 'sum_title' ) + $avg( 'sum_description' ) + $avg( 'sum_keyword' ) + $avg( 'sum_headings' ) ) / 4 );
		$content = (int) round( ( $avg( 'sum_content' ) + $avg( 'sum_readability' ) ) / 2 );
		$images  = $avg( 'sum_images' );

		$issue_counts   = $this->count_open_issues_by_severity();
		$duplicate_count = $this->count_open_issues_by_type( array( 'duplicate_title', 'duplicate_description' ) );
		$technical_penalty = min( 100, ( ( (int) ( $acc['posts_with_broken_links'] ?? 0 ) + $duplicate_count ) / $count ) * 100 );
		$technical = (int) round( max( 0, 100 - $technical_penalty ) );

		$categories = array(
			'technical' => $technical,
			'on_page'   => $on_page,
			'content'   => $content,
			'schema'    => 100,
			'images'    => $images,
		);

		$overall_sum   = 0;
		$overall_weight = 0;
		foreach ( self::CATEGORY_WEIGHTS as $key => $weight ) {
			$overall_sum   += ( $categories[ $key ] ?? 0 ) * $weight;
			$overall_weight += $weight;
		}
		$overall = $overall_weight > 0 ? (int) round( $overall_sum / $overall_weight ) : 0;

		$summary = array(
			'overall'             => max( 0, min( 100, $overall ) ),
			'categories'          => $categories,
			'issues_by_severity'  => $issue_counts,
			'last_scan_at'        => current_time( 'mysql', true ),
			'items_scanned'       => $count,
		);

		update_option( self::SUMMARY_OPTION, $summary, false );

		$scan_id = (int) get_option( self::CURRENT_SCAN_OPTION, 0 );
		if ( $scan_id ) {
			$table = Migrator::table( 'scans' );
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $table is from Migrator::table(), custom plugin table, no core API exists; caching is a possible future optimization.
				$table,
				array(
					'status'           => 'completed',
					'finished_at'      => current_time( 'mysql', true ),
					'results_summary'  => wp_json_encode( $summary ),
				),
				array( 'id' => $scan_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		$activity = Migrator::table( 'activity' );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $activity is from Migrator::table(), custom plugin table, no core API exists.
			$activity,
			array(
				'event_type'  => 'scan_completed',
				'description' => sprintf(
					/* translators: 1: number of pages scanned, 2: number of open issues */
					__( 'SEO scan completed: %1$d pages scanned, %2$d issues found.', 'ai-seo-autopilot' ),
					$count,
					array_sum( $issue_counts )
				),
				'user_id'     => get_current_user_id() ?: null,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%s' )
		);

		delete_option( self::ACCUMULATOR_OPTION );
		delete_option( self::CURRENT_SCAN_OPTION );
	}

	private function detect_site_wide_duplicates(): void {
		global $wpdb;

		$index_table  = Migrator::table( 'index' );
		$issues_table = Migrator::table( 'issues' );

		// Clear previously-detected duplicate issues; they're recomputed
		// fresh on every full scan pass.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$issues_table} WHERE issue_type = %s", 'duplicate_title' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $issues_table is from Migrator::table(), custom plugin table, no core API exists.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$issues_table} WHERE issue_type = %s", 'duplicate_description' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $issues_table is from Migrator::table(), custom plugin table, no core API exists.

		$this->insert_duplicate_issues( 'title', 'duplicate_title', __( 'Duplicate SEO title', 'ai-seo-autopilot' ), __( 'This title is used by more than one page.', 'ai-seo-autopilot' ) );
		$this->insert_duplicate_issues( 'description_hash', 'duplicate_description', __( 'Duplicate meta description', 'ai-seo-autopilot' ), __( 'This meta description is used by more than one page.', 'ai-seo-autopilot' ) );
	}

	private function insert_duplicate_issues( string $column, string $issue_type, string $title, string $description ): void {
		global $wpdb;

		$index_table = Migrator::table( 'index' );

		$groups = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $column is only ever called internally with the hardcoded literals 'title' or 'description_hash' (see detect_site_wide_duplicates()), never user input; $index_table is from Migrator::table(); custom plugin table, no core API exists.
			"SELECT {$column} AS value, GROUP_CONCAT(object_id) AS ids, GROUP_CONCAT(object_type) AS types FROM {$index_table} WHERE {$column} IS NOT NULL AND {$column} != '' GROUP BY {$column} HAVING COUNT(*) > 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is a hardcoded literal ('title'/'description_hash'), $index_table is from Migrator::table(), neither is user input.
			ARRAY_A
		);

		if ( empty( $groups ) ) {
			return;
		}

		$issues = array();

		foreach ( $groups as $group ) {
			$ids   = explode( ',', $group['ids'] );
			$types = explode( ',', $group['types'] );

			foreach ( $ids as $index => $object_id ) {
				$post = get_post( (int) $object_id );
				if ( ! $post ) {
					continue;
				}
				$issues[] = $this->issue( $post, $issue_type, 'on_page', 'medium', $title, $description );
			}
		}

		if ( ! empty( $issues ) ) {
			$this->insert_issues( $issues );
		}
	}

	/**
	 * @return array{high:int,medium:int,low:int}
	 */
	private function count_open_issues_by_severity(): array {
		global $wpdb;

		$table = Migrator::table( 'issues' );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is from Migrator::table(), custom plugin table, no core API exists; caching is a possible future optimization.
			"SELECT severity, COUNT(*) AS c FROM {$table} WHERE status = 'open' GROUP BY severity", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from Migrator::table(), not user input.
			ARRAY_A
		);

		$counts = array(
			'high'   => 0,
			'medium' => 0,
			'low'    => 0,
		);

		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row['severity'] ] ) ) {
				$counts[ $row['severity'] ] = (int) $row['c'];
			}
		}

		return $counts;
	}

	/**
	 * @param string[] $types
	 */
	private function count_open_issues_by_type( array $types ): int {
		global $wpdb;

		$table        = Migrator::table( 'issues' );
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is from Migrator::table(), custom plugin table, no core API exists; caching is a possible future optimization.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = 'open' AND issue_type IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $table is from Migrator::table(); $placeholders is a runtime-built '%s,%s,...' string matching count($types), the standard WP pattern for a dynamic IN() clause, which the sniff can't statically verify.
				...$types
			)
		);
	}

	/**
	 * @return array{overall:?int,categories:array<string,int>,issues_by_severity:array{high:int,medium:int,low:int},last_scan_at:?string}|null
	 */
	public function get_latest_summary(): ?array {
		$summary = get_option( self::SUMMARY_OPTION, null );

		return is_array( $summary ) ? $summary : null;
	}

	/**
	 * Issue types the AI can fix, mapped to the SEO meta field it rewrites.
	 */
	private const AI_FIXABLE = array(
		'missing_title'         => 'title',
		'duplicate_title'       => 'title',
		'missing_description'   => 'description',
		'duplicate_description' => 'description',
	);

	public static function ai_fix_field( string $issue_type ): ?string {
		return self::AI_FIXABLE[ $issue_type ] ?? null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get_issue( int $issue_id ): ?array {
		global $wpdb;

		$table = Migrator::table( 'issues' );

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is from Migrator::table(), custom plugin table, no core API exists.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from Migrator::table(), not user input.
				$issue_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	public function resolve_issue( int $issue_id ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $table is from Migrator::table(), custom plugin table, no core API exists.
			Migrator::table( 'issues' ),
			array(
				'status'      => 'resolved',
				'resolved_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $issue_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public function count_open_issues(): int {
		global $wpdb;

		$table = Migrator::table( 'issues' );

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is from Migrator::table(), custom plugin table, no core API exists; caching is a possible future optimization.
			"SELECT COUNT(*) FROM {$table} WHERE status = 'open'"
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function get_open_issues( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table = Migrator::table( 'issues' );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is from Migrator::table(), custom plugin table, no core API exists; caching is a possible future optimization.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'open' ORDER BY FIELD(severity,'high','medium','low'), detected_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from Migrator::table(), not user input.
				$limit,
				$offset
			),
			ARRAY_A
		);

		return (array) $rows;
	}
}
