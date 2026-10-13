<?php
/**
 * Main dashboard page.
 *
 * @package Scvx\Admin\Views
 */

namespace Scvx\Admin\Views;

use Scvx\Admin\Menu;
use Scvx\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DashboardView implements View {

	public function render(): void {
		( new HeaderPartial( 'dashboard' ) )->render();

		$plugin    = Plugin::instance();
		$auditor   = $plugin->get( 'seo.auditor' );
		$usage     = $plugin->get( 'ai.usage' );
		$providers = $plugin->get( 'ai.providers' );

		$summary = $auditor ? $auditor->get_latest_summary() : null;

		$overall     = $summary['overall'] ?? null;
		$categories  = $summary['categories'] ?? array();
		$by_severity = $summary['issues_by_severity'] ?? array(
			'high'   => 0,
			'medium' => 0,
			'low'    => 0,
		);
		$last_scan_at = $summary['last_scan_at'] ?? null;

		$usage_summary = $usage ? $usage->get_summary( 30 ) : array(
			'requests' => 0,
			'tokens'   => 0,
			'cost'     => 0,
		);

		$ai_configured = $providers ? $providers->is_configured() : false;
		?>
		<div class="scvx-page">

			<div class="scvx-hero">
				<div class="scvx-hero__score">
					<div class="scvx-score-ring" data-score="<?php echo esc_attr( $overall ?? 0 ); ?>">
						<span class="scvx-score-ring__value"><?php echo null === $overall ? '—' : esc_html( (string) $overall ); ?></span>
					</div>
					<div>
						<h1 class="scvx-hero__title"><?php esc_html_e( 'SEO Health Score', 'scanvexa-seo' ); ?></h1>
						<p class="scvx-hero__subtitle">
							<?php
							if ( $last_scan_at ) {
								printf(
									/* translators: %s: human-readable time since last scan */
									esc_html__( 'Last scanned %s ago', 'scanvexa-seo' ),
									esc_html( human_time_diff( strtotime( $last_scan_at ) ) )
								);
							} else {
								esc_html_e( 'No scan has run yet.', 'scanvexa-seo' );
							}
							?>
						</p>
					</div>
				</div>
				<button type="button" class="scvx-button scvx-button--primary" id="scvx-run-scan">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<?php esc_html_e( 'Run SEO Scan', 'scanvexa-seo' ); ?>
				</button>
			</div>

			<div class="scvx-grid scvx-grid--categories">
				<?php
				$labels = array(
					'technical' => __( 'Technical SEO', 'scanvexa-seo' ),
					'on_page'   => __( 'On-Page SEO', 'scanvexa-seo' ),
					'content'   => __( 'Content', 'scanvexa-seo' ),
					'schema'    => __( 'Schema', 'scanvexa-seo' ),
					'images'    => __( 'Images', 'scanvexa-seo' ),
				);
				foreach ( $labels as $key => $label ) :
					$value = $categories[ $key ] ?? null;
					?>
					<div class="scvx-mini-stat">
						<span class="scvx-mini-stat__label"><?php echo esc_html( $label ); ?></span>
						<span class="scvx-mini-stat__value"><?php echo null === $value ? '—' : esc_html( (string) $value ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="scvx-columns">
				<div class="scvx-card">
					<div class="scvx-card__header">
						<h3 class="scvx-card__title"><?php esc_html_e( 'Problems Found', 'scanvexa-seo' ); ?></h3>
					</div>
					<div class="scvx-severity-row">
						<div class="scvx-severity scvx-severity--high">
							<span class="scvx-severity__count"><?php echo esc_html( (string) $by_severity['high'] ); ?></span>
							<span class="scvx-severity__label"><?php esc_html_e( 'High', 'scanvexa-seo' ); ?></span>
						</div>
						<div class="scvx-severity scvx-severity--medium">
							<span class="scvx-severity__count"><?php echo esc_html( (string) $by_severity['medium'] ); ?></span>
							<span class="scvx-severity__label"><?php esc_html_e( 'Medium', 'scanvexa-seo' ); ?></span>
						</div>
						<div class="scvx-severity scvx-severity--low">
							<span class="scvx-severity__count"><?php echo esc_html( (string) $by_severity['low'] ); ?></span>
							<span class="scvx-severity__label"><?php esc_html_e( 'Low', 'scanvexa-seo' ); ?></span>
						</div>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::DASHBOARD_SLUG . '&view=issues' ) ); ?>" class="scvx-link">
						<?php esc_html_e( 'View all issues', 'scanvexa-seo' ); ?> →
					</a>
				</div>

				<div class="scvx-card">
					<div class="scvx-card__header">
						<h3 class="scvx-card__title"><?php esc_html_e( 'AI Usage (last 30 days)', 'scanvexa-seo' ); ?></h3>
					</div>
					<?php if ( ! $ai_configured ) : ?>
						<p class="scvx-card__description">
							<?php esc_html_e( 'Connect an AI provider to start generating titles, meta descriptions, ALT text and more.', 'scanvexa-seo' ); ?>
						</p>
						<a class="scvx-button scvx-button--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::SETTINGS_SLUG . '&tab=ai' ) ); ?>">
							<?php esc_html_e( 'Connect AI Provider', 'scanvexa-seo' ); ?>
						</a>
					<?php else : ?>
						<div class="scvx-usage-row">
							<div>
								<span class="scvx-usage-row__value"><?php echo esc_html( (string) $usage_summary['requests'] ); ?></span>
								<span class="scvx-usage-row__label"><?php esc_html_e( 'Requests', 'scanvexa-seo' ); ?></span>
							</div>
							<div>
								<span class="scvx-usage-row__value"><?php echo esc_html( number_format_i18n( $usage_summary['tokens'] ) ); ?></span>
								<span class="scvx-usage-row__label"><?php esc_html_e( 'Tokens', 'scanvexa-seo' ); ?></span>
							</div>
							<div>
								<span class="scvx-usage-row__value">$<?php echo esc_html( number_format( (float) $usage_summary['cost'], 2 ) ); ?></span>
								<span class="scvx-usage-row__label"><?php esc_html_e( 'Est. Cost', 'scanvexa-seo' ); ?></span>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>


		</div>
		<?php
	}
}
