<?php
/**
 * Plugin Name:       Scanvexa SEO — AI Website Analyzer
 * Description:       AI-powered SEO agent for WordPress. Connect your own AI provider, scan your site, and let AI analyze and improve your SEO.
 * Version:           1.0.2
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Author:            Mveous
 * Author URI:        https://mveous.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       scanvexa-seo
 * Domain Path:       /languages
 *
 * @package Scvx
 */

namespace Scvx;

if (!defined('ABSPATH')) {
	exit;
}

// -----------------------------------------------------------------------
// Core constants.
// -----------------------------------------------------------------------

define('SCVX_VERSION', '1.0.2');
define('SCVX_DB_VERSION', '1.0.0');
define('SCVX_FILE', __FILE__);
define('SCVX_DIR', plugin_dir_path(__FILE__));
define('SCVX_URL', plugin_dir_url(__FILE__));
define('SCVX_BASENAME', plugin_basename(__FILE__));
define('SCVX_TEXT_DOMAIN', 'scanvexa-seo');
define('SCVX_MIN_PHP', '8.0');
define('SCVX_MIN_WP', '6.0');

// -----------------------------------------------------------------------
// Environment guard. Fail soft, never fatal.
// -----------------------------------------------------------------------

if (version_compare(PHP_VERSION, SCVX_MIN_PHP, '<')) {
	add_action(
		'admin_notices',
		function () {
			if (!current_user_can('activate_plugins')) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__('Scanvexa SEO — AI Website Analyzer requires PHP %1$s or higher. You are running PHP %2$s. The plugin has been disabled.', 'scanvexa-seo'),
						SCVX_MIN_PHP,
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

if (file_exists(SCVX_DIR . 'vendor/autoload.php')) {
	require_once SCVX_DIR . 'vendor/autoload.php';
}

require_once SCVX_DIR . 'includes/Core/Autoloader.php';
Core\Autoloader::register('Scvx\\', SCVX_DIR . 'includes/');

// -----------------------------------------------------------------------
// Activation / deactivation.
// -----------------------------------------------------------------------

register_activation_hook(__FILE__, array(Core\Activator::class, 'activate'));
register_deactivation_hook(__FILE__, array(Core\Deactivator::class, 'deactivate'));

// -----------------------------------------------------------------------
// Bootstrap.
// -----------------------------------------------------------------------

/**
 * Returns the single plugin instance.
 *
 * Exposed as a function (not a global) so third-party extensions have a
 * stable, documented entry point without reaching into globals.
 *
 * @return Core\Plugin
 */
function scvx(): Core\Plugin
{
	return Core\Plugin::instance();
}

add_action(
	'plugins_loaded',
	static function () {
		scvx()->boot();
	},
	5
);
