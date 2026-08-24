<?php
/**
 * Free vs Pro feature comparison page.
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

$active_tab = 'upgrade';
require __DIR__ . '/partials/header.php';

$upgrade = Plugin::instance()->get( 'upgrade' );

$categories = array(
	'seo'         => __( 'SEO', 'ai-seo-autopilot' ),
	'ai'          => __( 'AI', 'ai-seo-autopilot' ),
	'schema'      => __( 'Schema', 'ai-seo-autopilot' ),
	'automation'  => __( 'Automation', 'ai-seo-autopilot' ),
	'technical'   => __( 'Technical SEO', 'ai-seo-autopilot' ),
	'reports'     => __( 'Reports & Search Console', 'ai-seo-autopilot' ),
	'woocommerce' => __( 'WooCommerce', 'ai-seo-autopilot' ),
	'local'       => __( 'Local SEO', 'ai-seo-autopilot' ),
	'multilingual' => __( 'Multilingual', 'ai-seo-autopilot' ),
);
?>
<div class="ai-seo-page ai-seo-page--upgrade">
	<div class="ai-seo-upgrade-hero">
		<h1><?php esc_html_e( 'AI SEO Autopilot Free vs Pro', 'ai-seo-autopilot' ); ?></h1>
		<p><?php esc_html_e( 'Free gives you a working AI SEO toolkit. Pro turns it into a fully autonomous SEO agent for your website.', 'ai-seo-autopilot' ); ?></p>
	</div>

	<?php foreach ( $categories as $cat_key => $cat_label ) :
		$rows = $features->in_category( $cat_key );
		if ( empty( $rows ) ) {
			continue;
		}
		?>
		<div class="ai-seo-compare-section">
			<h2><?php echo esc_html( $cat_label ); ?></h2>
			<table class="ai-seo-compare-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Feature', 'ai-seo-autopilot' ); ?></th>
						<th><?php esc_html_e( 'Free', 'ai-seo-autopilot' ); ?></th>
						<th><?php esc_html_e( 'Pro', 'ai-seo-autopilot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $key => $feature ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $feature['name'] ); ?></strong>
								<p class="ai-seo-compare-table__desc"><?php echo esc_html( $feature['description'] ); ?></p>
							</td>
							<td class="ai-seo-compare-table__mark">
								<?php echo empty( $feature['pro'] ) ? '<span class="dashicons dashicons-yes-alt ai-seo-icon--yes"></span>' : '<span class="dashicons dashicons-lock ai-seo-icon--locked"></span>'; ?>
							</td>
							<td class="ai-seo-compare-table__mark">
								<span class="dashicons dashicons-yes-alt ai-seo-icon--yes"></span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endforeach; ?>

	<div class="ai-seo-upgrade-cta">
		<a class="ai-seo-button ai-seo-button--primary ai-seo-button--large" href="<?php echo esc_url( $upgrade->pricing_url( 'comparison_page' ) ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Upgrade to Pro', 'ai-seo-autopilot' ); ?>
		</a>
	</div>
</div>
