<?php
/**
 * WP-Cron scheduling. Keeps the site scan running in small batches in
 * the background so it never has to run synchronously on a page load,
 * and so large sites finish scanning over several cron ticks instead of
 * one long-running request.
 *
 * @package Scvx\Cron
 */

namespace Scvx\Cron;

use Scvx\Core\Plugin;
use Scvx\Core\Registrable;
use Scvx\SEO\Audit\SiteAuditor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scheduler implements Registrable {

	public const HOOK_SITE_SCAN = 'scvx/cron/site_scan';

	private const RESCAN_INTERVAL = DAY_IN_SECONDS;

	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( 'init', array( $this, 'maybe_schedule' ) );
		add_action( self::HOOK_SITE_SCAN, array( $this, 'run_site_scan_tick' ) );
	}

	/**
	 * @param array<string,array{interval:int,display:string}> $schedules
	 *
	 * @return array<string,array{interval:int,display:string}>
	 */
	public function add_schedules( array $schedules ): array {
		$schedules['scvx_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes (Scanvexa SEO — AI Website Analyzer)', 'scanvexa-seo' ),
		);

		return $schedules;
	}

	public function maybe_schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK_SITE_SCAN ) ) {
			wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'scvx_15min', self::HOOK_SITE_SCAN );
		}
	}

	/**
	 * Runs on every cron tick: continues an in-progress scan, or starts a
	 * fresh one if the last completed scan is older than the rescan
	 * interval. Either way, only one batch is processed per tick.
	 */
	public function run_site_scan_tick(): void {
		/** @var SiteAuditor|null $auditor */
		$auditor = Plugin::instance()->get( 'seo.auditor' );
		if ( ! $auditor ) {
			return;
		}

		if ( $auditor->is_scan_in_progress() ) {
			$auditor->run_batch( 100 );
			return;
		}

		$summary      = $auditor->get_latest_summary();
		$last_scan_at = $summary['last_scan_at'] ?? null;

		if ( null === $last_scan_at || ( time() - strtotime( $last_scan_at ) ) >= self::RESCAN_INTERVAL ) {
			$auditor->run_batch( 100 );
		}
	}
}
