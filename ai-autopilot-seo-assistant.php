<?php
/**
 * Plugin Name:       AI Autopilot SEO Assistant
 * Description:       AI-powered SEO agent for WordPress. Connect your own AI provider, scan your site, and let AI analyze and improve your SEO.
 * Version:           1.0.1
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Author:            Mveous
 * Author URI:        https://mveous.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-autopilot-seo-assistant
 * Domain Path:       /languages
 *
 * @package Aasa
 */

namespace Aasa;

if (!defined('ABSPATH')) {
	exit;
}

// -----------------------------------------------------------------------
// Core constants.
// -----------------------------------------------------------------------

define('AASA_VERSION', '1.0.1');
define('AASA_DB_VERSION', '1.0.0');
define('AASA_FILE', __FILE__);
define('AASA_DIR', plugin_dir_path(__FILE__));
define('AASA_URL', plugin_dir_url(__FILE__));
define('AASA_BASENAME', plugin_basename(__FILE__));
define('AASA_TEXT_DOMAIN', 'ai-autopilot-seo-assistant');
define('AASA_MIN_PHP', '8.0');
define('AASA_MIN_WP', '6.0');

// -----------------------------------------------------------------------
// Environment guard. Fail soft, never fatal.
// -----------------------------------------------------------------------

if (version_compare(PHP_VERSION, AASA_MIN_PHP, '<')) {
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
						__('AI Autopilot SEO Assistant requires PHP %1$s or higher. You are running PHP %2$s. The plugin has been disabled.', 'ai-autopilot-seo-assistant'),
						AASA_MIN_PHP,
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

if (file_exists(AASA_DIR . 'vendor/autoload.php')) {
	require_once AASA_DIR . 'vendor/autoload.php';
}

require_once AASA_DIR . 'includes/Core/Autoloader.php';
Core\Autoloader::register('Aasa\\', AASA_DIR . 'includes/');

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
function aasa(): Core\Plugin
{
	return Core\Plugin::instance();
}

add_action(
	'plugins_loaded',
	static function () {
		aasa()->boot();
	},
	5
);
