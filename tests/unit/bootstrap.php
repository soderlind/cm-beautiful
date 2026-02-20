<?php
/**
 * PHPUnit bootstrap file for Brain Monkey integration.
 *
 * @package cm-beautiful
 */

declare(strict_types=1);

// Composer autoloader.
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Define WordPress constants used by the plugin.
if (! defined('ABSPATH')) {
	define('ABSPATH', '/tmp/wordpress/');
}

// Plugin constants.
if (! defined('CMB_VERSION')) {
	define('CMB_VERSION', '1.0.2');
}
if (! defined('CMB_PLUGIN_FILE')) {
	define('CMB_PLUGIN_FILE', dirname(__DIR__, 2) . '/cm-beautiful.php');
}
if (! defined('CMB_PLUGIN_DIR')) {
	define('CMB_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
}
if (! defined('CMB_PLUGIN_URL')) {
	define('CMB_PLUGIN_URL', 'https://example.com/wp-content/plugins/cm-beautiful/');
}
if (! defined('CMB_PLUGIN_BASENAME')) {
	define('CMB_PLUGIN_BASENAME', 'cm-beautiful/cm-beautiful.php');
}

// Load classes under test.
require_once CMB_PLUGIN_DIR . 'includes/class-cmb-color.php';
