<?php
/**
 * Main dashboard view.
 *
 * @package AISEOAutopilot
 *
 * @var \AISEOAutopilot\Features\FeatureManager $features
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- this file is require()'d inside a method's local scope (see Menu::render_view()), not evaluated as a standalone global scope.

use AISEOAutopilot\Core\Plugin;

$active_tab = 'dashboard';
require __DIR__ . '/partials/header.php';

$plugin   = Plugin::instance();
$auditor  = $plugin->get( 'seo.auditor' );
$usage    = $plugin->get( 'ai.usage' );
$upgrade  = $plugin->get( 'upgrade' );
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

$automation_features = array( 'seo_autopilot', 'search_console', 'internal_linking' );
?>
<div class="ai-seo-page">

	<div class="ai-seo-hero">
		<div class="ai-seo-hero__score">
			<div class="ai-seo-score-ring" data-score="<?php echo esc_attr( $overall ?? 0 ); ?>">
				<span class="ai-seo-score-ring__value"><?php echo null === $overall ? '—' : esc_html( (string) $overall ); ?></span>
			</div>
			<div>
				<h1 class="ai-seo-hero__title"><?php esc_html_e( 'SEO Health Score', 'ai-seo-autopilot' ); ?></h1>
				<p class="ai-seo-hero__subtitle">
					<?php
					if ( $last_scan_at ) {
						printf(
							/* translators: %s: human-readable time since last scan */
							esc_html__( 'Last scanned %s ago', 'ai-seo-autopilot' ),
							esc_html( human_time_diff( strtotime( $last_scan_at ) ) )
						);
					} else {
						esc_html_e( 'No scan has run yet.', 'ai-seo-autopilot' );
					}
					?>
				</p>
			</div>
		</div>
		<button type="button" class="ai-seo-button ai-seo-button--primary" id="ai-seo-run-scan">
			<span class="dashicons dashicons-search" aria-hidden="true"></span>
			<?php esc_html_e( 'Run SEO Scan', 'ai-seo-autopilot' ); ?>
		</button>
	</div>

	<div class="ai-seo-grid ai-seo-grid--categories">
		<?php
		$labels = array(
			'technical' => __( 'Technical SEO', 'ai-seo-autopilot' ),
			'on_page'   => __( 'On-Page SEO', 'ai-seo-autopilot' ),
			'content'   => __( 'Content', 'ai-seo-autopilot' ),
			'schema'    => __( 'Schema', 'ai-seo-autopilot' ),
			'images'    => __( 'Images', 'ai-seo-autopilot' ),
		);
		foreach ( $labels as $key => $label ) :
			$value = $categories[ $key ] ?? null;
			?>
			<div class="ai-seo-mini-stat">
				<span class="ai-seo-mini-stat__label"><?php echo esc_html( $label ); ?></span>
				<span class="ai-seo-mini-stat__value"><?php echo null === $value ? '—' : esc_html( (string) $value ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="ai-seo-columns">
		<div class="ai-seo-card">
			<div class="ai-seo-card__header">
				<h3 class="ai-seo-card__title"><?php esc_html_e( 'Problems Found', 'ai-seo-autopilot' ); ?></h3>
			</div>
			<div class="ai-seo-severity-row">
				<div class="ai-seo-severity ai-seo-severity--high">
					<span class="ai-seo-severity__count"><?php echo esc_html( (string) $by_severity['high'] ); ?></span>
					<span class="ai-seo-severity__label"><?php esc_html_e( 'High', 'ai-seo-autopilot' ); ?></span>
				</div>
				<div class="ai-seo-severity ai-seo-severity--medium">
					<span class="ai-seo-severity__count"><?php echo esc_html( (string) $by_severity['medium'] ); ?></span>
					<span class="ai-seo-severity__label"><?php esc_html_e( 'Medium', 'ai-seo-autopilot' ); ?></span>
				</div>
				<div class="ai-seo-severity ai-seo-severity--low">
					<span class="ai-seo-severity__count"><?php echo esc_html( (string) $by_severity['low'] ); ?></span>
					<span class="ai-seo-severity__label"><?php esc_html_e( 'Low', 'ai-seo-autopilot' ); ?></span>
				</div>
			</div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \AISEOAutopilot\Admin\Menu::DASHBOARD_SLUG . '&view=issues' ) ); ?>" class="ai-seo-link">
				<?php esc_html_e( 'View all issues', 'ai-seo-autopilot' ); ?> →
			</a>
		</div>

		<div class="ai-seo-card">
			<div class="ai-seo-card__header">
				<h3 class="ai-seo-card__title"><?php esc_html_e( 'AI Usage (last 30 days)', 'ai-seo-autopilot' ); ?></h3>
			</div>
			<?php if ( ! $ai_configured ) : ?>
				<p class="ai-seo-card__description">
					<?php esc_html_e( 'Connect an AI provider to start generating titles, meta descriptions, ALT text and more.', 'ai-seo-autopilot' ); ?>
				</p>
				<a class="ai-seo-button ai-seo-button--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . \AISEOAutopilot\Admin\Menu::SETTINGS_SLUG . '&tab=ai' ) ); ?>">
					<?php esc_html_e( 'Connect AI Provider', 'ai-seo-autopilot' ); ?>
				</a>
			<?php else : ?>
				<div class="ai-seo-usage-row">
					<div>
						<span class="ai-seo-usage-row__value"><?php echo esc_html( (string) $usage_summary['requests'] ); ?></span>
						<span class="ai-seo-usage-row__label"><?php esc_html_e( 'Requests', 'ai-seo-autopilot' ); ?></span>
					</div>
					<div>
						<span class="ai-seo-usage-row__value"><?php echo esc_html( number_format_i18n( $usage_summary['tokens'] ) ); ?></span>
						<span class="ai-seo-usage-row__label"><?php esc_html_e( 'Tokens', 'ai-seo-autopilot' ); ?></span>
					</div>
					<div>
						<span class="ai-seo-usage-row__value">$<?php echo esc_html( number_format( (float) $usage_summary['cost'], 2 ) ); ?></span>
						<span class="ai-seo-usage-row__label"><?php esc_html_e( 'Est. Cost', 'ai-seo-autopilot' ); ?></span>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php
	$locked_pro_features = array();
	foreach ( $features->all() as $key => $feature ) {
		if ( empty( $feature['pro'] ) || $features->is_available( $key ) ) {
			continue; // Free-tier feature, or Pro is active and renders the live feature elsewhere.
		}
		$locked_pro_features[ $key ] = $feature;
	}
	?>
	<?php if ( ! empty( $locked_pro_features ) ) : ?>
		<div class="ai-seo-pro-section">
			<div class="ai-seo-pro-section__head">
				<div>
					<p class="ai-seo-pro-section__eyebrow">
						<?php
						printf(
							/* translators: %d: number of Pro tools */
							esc_html( _n( '%d tool included with Pro', '%d tools included with Pro', count( $locked_pro_features ), 'ai-seo-autopilot' ) ),
							count( $locked_pro_features )
						);
						?>
					</p>
					<h2 class="ai-seo-pro-section__title"><?php esc_html_e( 'AI Autopilot & Pro Modules', 'ai-seo-autopilot' ); ?></h2>
				</div>
				<a class="ai-seo-button ai-seo-button--primary" href="<?php echo esc_url( $upgrade->pricing_url( 'dashboard_pro_modules' ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Upgrade to Pro', 'ai-seo-autopilot' ); ?>
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</a>
			</div>
			<div class="ai-seo-grid ai-seo-grid--pro-tiles">
				<?php foreach ( $locked_pro_features as $key => $feature ) : ?>
					<?php $upgrade->render_locked_feature_tile( $key, $feature ); ?>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

</div>
