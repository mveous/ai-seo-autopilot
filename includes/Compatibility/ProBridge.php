<?php
/**
 * The stable integration surface Pro hooks into. Free never loads or
 * references Pro classes directly — Pro detects Free (via the
 * `ai_seo_autopilot()` function and `ai_seo_autopilot/loaded` action)
 * and registers itself through the documented filters/actions:
 *
 * - `ai_seo_autopilot/pro_active`        (bool)   — Pro reports its license state here.
 * - `ai_seo_autopilot/register_features` (action) — Pro registers/unlocks features here.
 * - `ai_seo_autopilot/register_ai_providers` (action)
 *
 * This class only handles the reverse direction: small, passive checks
 * Free performs about Pro's presence, without ever requiring Pro code.
 *
 * @package AISEOAutopilot\Compatibility
 */

namespace AISEOAutopilot\Compatibility;

use AISEOAutopilot\Core\Registrable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProBridge implements Registrable {

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render_unlicensed_notice' ) );
		add_filter( 'ai_seo_autopilot/plan_badge', array( $this, 'plan_badge' ) );
	}

	/**
	 * True once Pro is installed, active, and its license is valid.
	 */
	public function is_pro_active(): bool {
		return (bool) apply_filters( 'ai_seo_autopilot/pro_active', false );
	}

	/**
	 * True if the Pro plugin file is present and active, regardless of
	 * license state. Pro is expected to define this constant in its own
	 * bootstrap once it confirms Free is loaded and compatible.
	 */
	public function is_pro_installed(): bool {
		return defined( 'AI_SEO_AUTOPILOT_PRO_VERSION' );
	}

	public function pro_version(): ?string {
		return defined( 'AI_SEO_AUTOPILOT_PRO_VERSION' ) ? AI_SEO_AUTOPILOT_PRO_VERSION : null;
	}

	public function plan_badge( string $default ): string {
		return $this->is_pro_active() ? __( 'PRO', 'ai-seo-autopilot' ) : $default;
	}

	/**
	 * If Pro is installed but never reports itself as licensed (invalid
	 * or missing license), let the user know from the Free side too,
	 * since a silently-inactive Pro looks identical to "not installed".
	 */
	public function maybe_render_unlicensed_notice(): void {
		if ( ! $this->is_pro_installed() || $this->is_pro_active() ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'ai-seo-autopilot' ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'AI SEO Autopilot Pro is installed but is not currently licensed or active. Pro features are locked until a valid license is activated.', 'ai-seo-autopilot' ); ?></p>
		</div>
		<?php
	}
}
