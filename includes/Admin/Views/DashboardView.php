<?php
/**
 * Main dashboard page.
 *
 * @package Aasa\Admin\Views
 */

namespace Aasa\Admin\Views;

use Aasa\Admin\Menu;
use Aasa\Core\Plugin;

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
		<div class="aasa-page">

			<div class="aasa-hero">
				<div class="aasa-hero__score">
					<div class="aasa-score-ring" data-score="<?php echo esc_attr( $overall ?? 0 ); ?>">
						<span class="aasa-score-ring__value"><?php echo null === $overall ? '—' : esc_html( (string) $overall ); ?></span>
					</div>
					<div>
						<h1 class="aasa-hero__title"><?php esc_html_e( 'SEO Health Score', 'ai-autopilot-seo-assistant' ); ?></h1>
						<p class="aasa-hero__subtitle">
							<?php
							if ( $last_scan_at ) {
								printf(
									/* translators: %s: human-readable time since last scan */
									esc_html__( 'Last scanned %s ago', 'ai-autopilot-seo-assistant' ),
									esc_html( human_time_diff( strtotime( $last_scan_at ) ) )
								);
							} else {
								esc_html_e( 'No scan has run yet.', 'ai-autopilot-seo-assistant' );
							}
							?>
						</p>
					</div>
				</div>
				<button type="button" class="aasa-button aasa-button--primary" id="aasa-run-scan">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<?php esc_html_e( 'Run SEO Scan', 'ai-autopilot-seo-assistant' ); ?>
				</button>
			</div>

			<div class="aasa-grid aasa-grid--categories">
				<?php
				$labels = array(
					'technical' => __( 'Technical SEO', 'ai-autopilot-seo-assistant' ),
					'on_page'   => __( 'On-Page SEO', 'ai-autopilot-seo-assistant' ),
					'content'   => __( 'Content', 'ai-autopilot-seo-assistant' ),
					'schema'    => __( 'Schema', 'ai-autopilot-seo-assistant' ),
					'images'    => __( 'Images', 'ai-autopilot-seo-assistant' ),
				);
				foreach ( $labels as $key => $label ) :
					$value = $categories[ $key ] ?? null;
					?>
					<div class="aasa-mini-stat">
						<span class="aasa-mini-stat__label"><?php echo esc_html( $label ); ?></span>
						<span class="aasa-mini-stat__value"><?php echo null === $value ? '—' : esc_html( (string) $value ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="aasa-columns">
				<div class="aasa-card">
					<div class="aasa-card__header">
						<h3 class="aasa-card__title"><?php esc_html_e( 'Problems Found', 'ai-autopilot-seo-assistant' ); ?></h3>
					</div>
					<div class="aasa-severity-row">
						<div class="aasa-severity aasa-severity--high">
							<span class="aasa-severity__count"><?php echo esc_html( (string) $by_severity['high'] ); ?></span>
							<span class="aasa-severity__label"><?php esc_html_e( 'High', 'ai-autopilot-seo-assistant' ); ?></span>
						</div>
						<div class="aasa-severity aasa-severity--medium">
							<span class="aasa-severity__count"><?php echo esc_html( (string) $by_severity['medium'] ); ?></span>
							<span class="aasa-severity__label"><?php esc_html_e( 'Medium', 'ai-autopilot-seo-assistant' ); ?></span>
						</div>
						<div class="aasa-severity aasa-severity--low">
							<span class="aasa-severity__count"><?php echo esc_html( (string) $by_severity['low'] ); ?></span>
							<span class="aasa-severity__label"><?php esc_html_e( 'Low', 'ai-autopilot-seo-assistant' ); ?></span>
						</div>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::DASHBOARD_SLUG . '&view=issues' ) ); ?>" class="aasa-link">
						<?php esc_html_e( 'View all issues', 'ai-autopilot-seo-assistant' ); ?> →
					</a>
				</div>

				<div class="aasa-card">
					<div class="aasa-card__header">
						<h3 class="aasa-card__title"><?php esc_html_e( 'AI Usage (last 30 days)', 'ai-autopilot-seo-assistant' ); ?></h3>
					</div>
					<?php if ( ! $ai_configured ) : ?>
						<p class="aasa-card__description">
							<?php esc_html_e( 'Connect an AI provider to start generating titles, meta descriptions, ALT text and more.', 'ai-autopilot-seo-assistant' ); ?>
						</p>
						<a class="aasa-button aasa-button--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::SETTINGS_SLUG . '&tab=ai' ) ); ?>">
							<?php esc_html_e( 'Connect AI Provider', 'ai-autopilot-seo-assistant' ); ?>
						</a>
					<?php else : ?>
						<div class="aasa-usage-row">
							<div>
								<span class="aasa-usage-row__value"><?php echo esc_html( (string) $usage_summary['requests'] ); ?></span>
								<span class="aasa-usage-row__label"><?php esc_html_e( 'Requests', 'ai-autopilot-seo-assistant' ); ?></span>
							</div>
							<div>
								<span class="aasa-usage-row__value"><?php echo esc_html( number_format_i18n( $usage_summary['tokens'] ) ); ?></span>
								<span class="aasa-usage-row__label"><?php esc_html_e( 'Tokens', 'ai-autopilot-seo-assistant' ); ?></span>
							</div>
							<div>
								<span class="aasa-usage-row__value">$<?php echo esc_html( number_format( (float) $usage_summary['cost'], 2 ) ); ?></span>
								<span class="aasa-usage-row__label"><?php esc_html_e( 'Est. Cost', 'ai-autopilot-seo-assistant' ); ?></span>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>


		</div>
		<?php
	}
}
