<?php
/**
 * Plugin Name:       AI SEO Autopilot
 * Plugin URI:        https://aiseoautopilot.com
 * Description:       AI-powered SEO agent for WordPress. Connect your own AI provider, scan your site, and let AI analyze, recommend, and (with Pro) autonomously improve your SEO.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            AI SEO Autopilot
 * Author URI:        https://aiseoautopilot.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-seo-autopilot
 * Domain Path:       /languages
 *
 * @package AISEOAutopilot
 */

namespace AISEOAutopilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -----------------------------------------------------------------------
// Core constants.
// -----------------------------------------------------------------------

define( 'AI_SEO_AUTOPILOT_VERSION', '1.0.0' );
define( 'AI_SEO_AUTOPILOT_DB_VERSION', '1.0.0' );
define( 'AI_SEO_AUTOPILOT_FILE', __FILE__ );
define( 'AI_SEO_AUTOPILOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_SEO_AUTOPILOT_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_SEO_AUTOPILOT_BASENAME', plugin_basename( __FILE__ ) );
define( 'AI_SEO_AUTOPILOT_TEXT_DOMAIN', 'ai-seo-autopilot' );
define( 'AI_SEO_AUTOPILOT_MIN_PHP', '8.1' );
define( 'AI_SEO_AUTOPILOT_MIN_WP', '6.0' );

// -----------------------------------------------------------------------
// Environment guard. Fail soft, never fatal.
// -----------------------------------------------------------------------

if ( version_compare( PHP_VERSION, AI_SEO_AUTOPILOT_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__( 'AI SEO Autopilot requires PHP %1$s or higher. You are running PHP %2$s. The plugin has been disabled.', 'ai-seo-autopilot' ),
						AI_SEO_AUTOPILOT_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

// -----------------------------------------------------------------------
// Autoloading.
// -----------------------------------------------------------------------

if ( file_exists( AI_SEO_AUTOPILOT_DIR . 'vendor/autoload.php' ) ) {
	require_once AI_SEO_AUTOPILOT_DIR . 'vendor/autoload.php';
}

require_once AI_SEO_AUTOPILOT_DIR . 'includes/Core/Autoloader.php';
Core\Autoloader::register( 'AISEOAutopilot\\', AI_SEO_AUTOPILOT_DIR . 'includes/' );

// -----------------------------------------------------------------------
// Activation / deactivation.
// -----------------------------------------------------------------------

register_activation_hook( __FILE__, array( Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Core\Deactivator::class, 'deactivate' ) );

// -----------------------------------------------------------------------
// Bootstrap.
// -----------------------------------------------------------------------

/**
 * Returns the single plugin instance.
 *
 * Exposed as a function (not a global) so Pro and third parties have a
 * stable, documented entry point without reaching into globals.
 *
 * @return Core\Plugin
 */
function ai_seo_autopilot(): Core\Plugin {
	return Core\Plugin::instance();
}

add_action(
	'plugins_loaded',
	static function () {
		ai_seo_autopilot()->boot();
	},
	5
);
