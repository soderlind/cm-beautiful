<?php
/**
 * Color Me Beautiful – Plugin Orchestrator
 *
 * Singleton class that boots the plugin, wires dependencies, registers the
 * admin menu page, and handles asset enqueuing.
 *
 * @package    cm-beautiful
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central orchestrator for the Color Me Beautiful plugin.
 *
 * Settings live on the user profile page; this plugin has no top-level
 * admin menu entry of its own.
 *
 * @since 1.0.0
 */
final class CMB_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var   CMB_Plugin|null
	 */
	private static ?CMB_Plugin $instance = null;

	/**
	 * Whether init() has already run.
	 * Prevents duplicate hook registration if init() is called more than once.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	private bool $initialized = false;

	/**
	 * Profile fields handler.
	 *
	 * @since 1.0.0
	 * @var   CMB_Profile
	 */
	private CMB_Profile $profile;

	/**
	 * Admin-theme CSS injector.
	 *
	 * @since 1.0.0
	 * @var   CMB_Admin_Theme
	 */
	private CMB_Admin_Theme $admin_theme;

	/**
	 * Private constructor – use CMB_Plugin::instance() instead.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Return (and lazily create) the singleton instance.
	 *
	 * @since  1.0.0
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialise the plugin.
	 *
	 * Called on the `plugins_loaded` action so that WordPress and all other
	 * plugins are guaranteed to be loaded before any hooks are registered.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}
		$this->initialized = true;

		load_plugin_textdomain(
			'cm-beautiful',
			false,
			dirname( CMB_PLUGIN_BASENAME ) . '/languages'
		);

		$this->profile     = new CMB_Profile();
		$this->admin_theme = new CMB_Admin_Theme();

		$this->profile->register_hooks();
		$this->admin_theme->register_hooks();

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue styles and scripts on the appropriate admin screens.
	 *
	 * Profile / user-edit pages receive the WP Color Picker and the live-
	 * preview JS.
	 *
	 * @since  1.0.0
	 * @param  string $hook_suffix The current page's hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( in_array( $hook_suffix, [ 'profile.php', 'user-edit.php' ], true ) ) {
			// WP Color Picker (Iris) stylesheet is bundled with WordPress.
			wp_enqueue_style( 'wp-color-picker' );

			wp_enqueue_script(
				'cmb-profile-color-picker',
				CMB_PLUGIN_URL . 'assets/js/profile-color-picker.js',
				[ 'jquery', 'wp-color-picker' ],
				CMB_VERSION,
				true   // Load in footer.
			);

			// Pass preset hex values to JS so the swatch JS can resolve them.
			// array_map preserves keys; extract only the hex string.
			$presets_for_js = array_map(
				static fn( array $data ): string => $data[ 'hex' ] ?? '',
				CMB_Profile::PRESETS
			);

			wp_localize_script( 'cmb-profile-color-picker', 'cmbPresets', $presets_for_js );

			// Swatch styles are lightweight but reuse the same stylesheet.
			wp_enqueue_style(
				'cmb-admin',
				CMB_PLUGIN_URL . 'assets/css/admin.css',
				[ 'wp-color-picker' ],
				CMB_VERSION
			);
		}
	}
}
