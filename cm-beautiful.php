<?php
/**
 * Plugin Name:       Color Me Beautiful
 * Plugin URI:        https://github.com/soderlind/cm-beautiful
 * Description:       Personalise the WordPress admin with your own accent colour. Per-user preset dropdown and custom colour picker, applied via CSS custom properties on this plugin's admin screens.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.3
 * Author:            Per Soderlind
 * Author URI:        https://soderlind.no
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cm-beautiful
 * Domain Path:       /languages
 *
 * @package cm-beautiful
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Plugin constants
// ---------------------------------------------------------------------------

/** Plugin version string. */
define( 'CMB_VERSION', '1.0.0' );

/** Absolute path to the main plugin file. */
define( 'CMB_PLUGIN_FILE', __FILE__ );

/** Absolute path to the plugin root directory (trailing slash). */
define( 'CMB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/** Public URL of the plugin root directory (trailing slash). */
define( 'CMB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/** Plugin basename (e.g. cm-beautiful/cm-beautiful.php). */
define( 'CMB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ---------------------------------------------------------------------------
// Load dependencies
// ---------------------------------------------------------------------------

require_once CMB_PLUGIN_DIR . 'vendor/autoload.php';

require_once CMB_PLUGIN_DIR . 'includes/class-cmb-color.php';
require_once CMB_PLUGIN_DIR . 'includes/class-cmb-profile.php';
require_once CMB_PLUGIN_DIR . 'includes/class-cmb-admin-theme.php';
require_once CMB_PLUGIN_DIR . 'includes/class-cmb-plugin.php';

// ---------------------------------------------------------------------------
// GitHub updater
// ---------------------------------------------------------------------------

if ( ! class_exists( \Soderlind\WordPress\GitHubUpdater::class) ) {
	require_once CMB_PLUGIN_DIR . 'class-github-updater.php';
}

\Soderlind\WordPress\GitHubUpdater::init(
	github_url: 'https://github.com/soderlind/cm-beautiful',
	plugin_file: CMB_PLUGIN_FILE,
	plugin_slug: 'cm-beautiful',
	name_regex: '/cm-beautiful\.zip/',
	branch: 'main',
);

// ---------------------------------------------------------------------------
// Bootstrap
//
// Hooking into plugins_loaded (priority 10) ensures that:
//  – All other plugins are loaded before our hooks are registered.
//  – Text-domain loading happens at the correct point in the WP lifecycle.
// ---------------------------------------------------------------------------

add_action( 'plugins_loaded', [ CMB_Plugin::instance(), 'init' ] );
