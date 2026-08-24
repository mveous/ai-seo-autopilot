<?php
/**
 * Upgrade messaging: pricing URL and the shared "locked Pro feature" card
 * renderer used throughout the dashboard and settings screens.
 *
 * @package AISEOAutopilot\Features
 */

namespace AISEOAutopilot\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UpgradeManager {

	public function pricing_url( string $utm_content = 'general' ): string {
		$url = 'https://aiseoautopilot.com/pricing';

		$url = add_query_arg(
			array(
				'utm_source'   => 'plugin',
				'utm_medium'   => 'admin',
				'utm_campaign' => 'upgrade',
				'utm_content'  => $utm_content,
			),
			$url
		);

		/**
		 * Filters the upgrade/pricing URL, e.g. so a white-labeled build
		 * can point to a different destination.
		 *
		 * @param string $url
		 * @param string $utm_content
		 */
		return (string) apply_filters( 'ai_seo_autopilot/pricing_url', $url, $utm_content );
	}

	/**
	 * Render a polished locked-feature card for a feature registered in
	 * FeatureManager. Safe to call even if the feature is actually
	 * available — callers should check FeatureManager::is_available()
	 * first and only render this branch when it's false.
	 *
	 * @param array{name:string,description:string,benefits?:string[],icon?:string} $feature
	 */
	public function render_locked_feature_card( string $feature_key, array $feature ): void {
		$name        = $feature['name'] ?? $feature_key;
		$description = $feature['description'] ?? '';
		$benefits    = $feature['benefits'] ?? array();
		$icon        = $feature['icon'] ?? 'dashicons-lock';
		$url         = $this->pricing_url( $feature_key );
		?>
		<div class="ai-seo-card ai-seo-card--locked" data-feature="<?php echo esc_attr( $feature_key ); ?>">
			<div class="ai-seo-card__header">
				<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
				<h3 class="ai-seo-card__title"><?php echo esc_html( $name ); ?></h3>
				<span class="ai-seo-badge ai-seo-badge--pro">
					<span class="dashicons dashicons-lock" aria-hidden="true"></span>
					<?php esc_html_e( 'PRO', 'ai-seo-autopilot' ); ?>
				</span>
			</div>
			<p class="ai-seo-card__description"><?php echo esc_html( $description ); ?></p>
			<?php if ( ! empty( $benefits ) ) : ?>
				<ul class="ai-seo-card__benefits">
					<?php foreach ( $benefits as $benefit ) : ?>
						<li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php echo esc_html( $benefit ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<a class="ai-seo-button ai-seo-button--upgrade" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php
				printf(
					/* translators: %s: feature name */
					esc_html__( 'Unlock %s', 'ai-seo-autopilot' ),
					esc_html( $name )
				);
				?>
			</a>
		</div>
		<?php
	}

	/**
	 * Render a compact, fixed-height locked-feature tile for dense grids
	 * (the dashboard's "Pro Modules" section, which shows every locked
	 * Pro feature at once). Unlike render_locked_feature_card(), this
	 * intentionally drops the benefits list and full-width CTA button so
	 * every tile in the grid is the same height regardless of how much
	 * copy a given feature has — use render_locked_feature_card() instead
	 * when a single feature needs its own detailed upsell (e.g. a locked
	 * settings tab).
	 *
	 * @param array{name:string,description:string,icon?:string} $feature
	 */
	public function render_locked_feature_tile( string $feature_key, array $feature ): void {
		$name        = $feature['name'] ?? $feature_key;
		$description = $feature['description'] ?? '';
		$icon        = $feature['icon'] ?? 'dashicons-star-filled';
		$url         = $this->pricing_url( $feature_key );
		?>
		<div class="ai-seo-pro-tile" data-feature="<?php echo esc_attr( $feature_key ); ?>">
			<div class="ai-seo-pro-tile__top">
				<span class="ai-seo-pro-tile__icon">
					<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
				</span>
				<span class="ai-seo-badge ai-seo-badge--pro ai-seo-badge--sm">
					<span class="dashicons dashicons-lock" aria-hidden="true"></span>
					<?php esc_html_e( 'PRO', 'ai-seo-autopilot' ); ?>
				</span>
			</div>
			<h3 class="ai-seo-pro-tile__title"><?php echo esc_html( $name ); ?></h3>
			<p class="ai-seo-pro-tile__description"><?php echo esc_html( $description ); ?></p>
			<a class="ai-seo-pro-tile__unlock" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Unlock', 'ai-seo-autopilot' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
			</a>
		</div>
		<?php
	}
}
