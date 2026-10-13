<?php
/**
 * All open SEO issues, paginated.
 *
 * @package Scvx\Admin\Views
 */

namespace Scvx\Admin\Views;

use Scvx\Admin\Menu;
use Scvx\Core\Plugin;
use Scvx\SEO\Audit\SiteAuditor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IssuesView implements View {

	public function render(): void {
		( new HeaderPartial( 'dashboard' ) )->render();

		$plugin       = Plugin::instance();
		$auditor      = $plugin->get( 'seo.auditor' );
		$providers    = $plugin->get( 'ai.providers' );
		$capabilities = $plugin->get( 'capabilities' );

		$ai_configured = $providers ? $providers->is_configured() : false;
		$can_use_ai    = $capabilities ? $capabilities->current_user_can( 'use_ai_generation' ) : current_user_can( 'edit_posts' );

		$per_page     = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$total        = $auditor ? $auditor->count_open_issues() : 0;
		$total_pages  = max( 1, (int) ceil( $total / $per_page ) );
		$current_page = min( $current_page, $total_pages );
		$issues       = $auditor ? $auditor->get_open_issues( $per_page, ( $current_page - 1 ) * $per_page ) : array();

		$base_url = admin_url( 'admin.php?page=' . Menu::DASHBOARD_SLUG . '&view=issues' );

		$severity_labels = array(
			'high'   => __( 'High', 'scanvexa-seo' ),
			'medium' => __( 'Medium', 'scanvexa-seo' ),
			'low'    => __( 'Low', 'scanvexa-seo' ),
		);
		$category_labels = array(
			'technical' => __( 'Technical SEO', 'scanvexa-seo' ),
			'on_page'   => __( 'On-Page SEO', 'scanvexa-seo' ),
			'content'   => __( 'Content', 'scanvexa-seo' ),
			'schema'    => __( 'Schema', 'scanvexa-seo' ),
			'images'    => __( 'Images', 'scanvexa-seo' ),
		);
		?>
		<div class="scvx-page">

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::DASHBOARD_SLUG ) ); ?>" class="scvx-link">
					← <?php esc_html_e( 'Back to dashboard', 'scanvexa-seo' ); ?>
				</a>
			</p>

			<div class="scvx-card">
				<div class="scvx-card__header">
					<h3 class="scvx-card__title">
						<?php
						printf(
							/* translators: %s: number of open issues */
							esc_html__( 'Open Issues (%s)', 'scanvexa-seo' ),
							esc_html( number_format_i18n( $total ) )
						);
						?>
					</h3>
				</div>

				<?php if ( empty( $issues ) ) : ?>
					<p class="scvx-card__description">
						<?php esc_html_e( 'No open issues. Run an SEO scan from the dashboard to check your site.', 'scanvexa-seo' ); ?>
					</p>
				<?php else : ?>
					<table class="widefat striped scvx-issues-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Severity', 'scanvexa-seo' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Issue', 'scanvexa-seo' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Page', 'scanvexa-seo' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Category', 'scanvexa-seo' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Detected', 'scanvexa-seo' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Action', 'scanvexa-seo' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $issues as $issue ) : ?>
								<?php
								$severity  = (string) $issue['severity'];
								$object_id = (int) $issue['object_id'];
								$edit_link = $object_id ? get_edit_post_link( $object_id ) : '';
								$obj_title = $object_id ? get_the_title( $object_id ) : '';
								$ai_field  = SiteAuditor::ai_fix_field( (string) $issue['issue_type'] );
								?>
								<tr>
									<td>
										<span class="scvx-issue-severity scvx-issue-severity--<?php echo esc_attr( $severity ); ?>">
											<?php echo esc_html( $severity_labels[ $severity ] ?? $severity ); ?>
										</span>
									</td>
									<td>
										<strong><?php echo esc_html( (string) $issue['title'] ); ?></strong>
										<?php if ( ! empty( $issue['description'] ) ) : ?>
											<br><span class="description"><?php echo esc_html( (string) $issue['description'] ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $edit_link ) : ?>
											<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( '' !== $obj_title ? $obj_title : '#' . $object_id ); ?></a>
										<?php elseif ( $object_id ) : ?>
											<?php echo esc_html( '' !== $obj_title ? $obj_title : '#' . $object_id ); ?>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $category_labels[ $issue['category'] ] ?? (string) $issue['category'] ); ?></td>
									<td>
										<?php
										printf(
											/* translators: %s: human-readable time difference */
											esc_html__( '%s ago', 'scanvexa-seo' ),
											esc_html( human_time_diff( strtotime( (string) $issue['detected_at'] ) ) )
										);
										?>
									</td>
									<td class="scvx-issue-action">
										<?php if ( $ai_field && $object_id && $can_use_ai && current_user_can( 'edit_post', $object_id ) ) : ?>
											<?php if ( $ai_configured ) : ?>
												<button type="button" class="scvx-button scvx-button--secondary scvx-fix-issue" data-issue-id="<?php echo esc_attr( (string) $issue['id'] ); ?>">
													<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
													<?php esc_html_e( 'Fix with AI', 'scanvexa-seo' ); ?>
												</button>
											<?php else : ?>
												<a class="scvx-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::SETTINGS_SLUG . '&tab=ai' ) ); ?>">
													<?php esc_html_e( 'Connect AI to fix', 'scanvexa-seo' ); ?>
												</a>
											<?php endif; ?>
										<?php elseif ( $edit_link ) : ?>
											<a class="scvx-link" href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit page', 'scanvexa-seo' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $total_pages > 1 ) : ?>
						<div class="tablenav"><div class="tablenav-pages">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'    => add_query_arg( 'paged', '%#%', $base_url ),
										'format'  => '',
										'current' => $current_page,
										'total'   => $total_pages,
									)
								)
							);
							?>
						</div></div>
					<?php endif; ?>
				<?php endif; ?>
			</div>

		</div>
		<?php
	}
}
