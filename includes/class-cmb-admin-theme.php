<?php
/**
 * Color Me Beautiful – Admin Theme Injector
 *
 * Architecture overview
 * ─────────────────────
 * Admin chrome CSS rules are emitted ONLY when a concrete accent colour is
 * resolved (a preset or custom colour is saved).  For "Follow WordPress",
 * nothing is emitted — WordPress manages its own admin chrome via its own
 * --wp-admin-theme-color CSS custom property, and we do not interfere.
 *
 * When a concrete accent is active, every chrome rule is expressed as:
 *   background: var(--cmb-accent, var(--wp-admin-theme-color, #2271b1))
 * so JS can update only the CSS custom properties on :root for an instant,
 * page-reload-free live preview.
 *
 * Night mode (filter: invert + hue-rotate on html) is orthogonal to the
 * colour choice and is emitted independently.
 *
 * On admin_head priority 1 (before our override at priority 10), a tiny inline
 * script saves the native WP theme colour into window._cmbNativeWpColor so the
 * JS live-preview can correctly revert to "Follow WordPress" at any time.
 *
 * @package    cm-beautiful
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves and injects CSS custom properties for the user's colour preference.
 *
 * @since 1.0.0
 */
class CMB_Admin_Theme {

	/**
	 * Register WordPress hooks.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_hooks(): void {
		/*
		 * Priority 1: capture --wp-admin-theme-color BEFORE our :root override
		 * (priority 10) so window._cmbNativeWpColor always reflects the active
		 * WP colour scheme, not our injected value.
		 */
		add_action( 'admin_head', [ $this, 'inject_native_color_capture' ], 1 );
		add_action( 'admin_head', [ $this, 'inject_css_vars' ] );
	}

	/**
	 * Emit a tiny inline script that reads --wp-admin-theme-color from the
	 * active WP colour-scheme stylesheet (before our override) and stores it
	 * as window._cmbNativeWpColor for the JS live-preview to use.
	 *
	 * Runs at admin_head priority 1, before inject_css_vars() at priority 10.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function inject_native_color_capture(): void {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "<script>window._cmbNativeWpColor=(getComputedStyle(document.documentElement).getPropertyValue('--wp-admin-theme-color')||'').trim()||'#2271b1';</script>\n";
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Output the <style> tag that drives the admin chrome colouring.
	 *
	 * Emits chrome CSS only when a concrete accent colour is resolved:
	 *  – .cmb-beautiful scoped vars.
	 *  – :root block overriding --wp-admin-theme-color + all --cmb-* variants.
	 *  – Admin chrome CSS rules.
	 *
	 * For "Follow WordPress" (no concrete accent) nothing is emitted for chrome;
	 * WP handles its own admin chrome and we do not interfere.
	 *
	 * Night-mode CSS is appended independently of the colour choice.
	 * If there is nothing to output, the <style> tag is skipped entirely.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function inject_css_vars(): void {
		$user_id       = get_current_user_id();
		$preset_key    = (string) get_user_meta( $user_id, CMB_Profile::META_PRESET, true );
		$custom_accent = (string) get_user_meta( $user_id, CMB_Profile::META_CUSTOM_ACCENT, true );

		if ( '' === $preset_key || ! array_key_exists( $preset_key, CMB_Profile::PRESETS ) ) {
			$preset_key = 'follow_wp';
		}

		// Resolve a concrete hex accent, if any.
		$accent = '';

		if ( '' !== $custom_accent && CMB_Color::is_valid_hex( $custom_accent ) ) {
			$accent = CMB_Color::normalize_hex( $custom_accent );
		} elseif ( 'follow_wp' !== $preset_key ) {
			$preset_hex = CMB_Profile::PRESETS[ $preset_key ][ 'hex' ] ?? '';
			if ( '' !== $preset_hex ) {
				$accent = $preset_hex;
			}
		}

		$css = '';

		// ── Concrete accent only: .cmb-beautiful vars + :root override + chrome ─
		// When "Follow WordPress" is active, WordPress handles its own admin chrome
		// via --wp-admin-theme-color. Emitting our rules would only interfere.
		if ( '' !== $accent ) {
			$a    = esc_attr( $accent );
			$ec   = esc_attr( CMB_Color::contrast_color( $accent ) );
			$css .= ".cmb-beautiful { --cmb-accent: {$a}; --cmb-accent-contrast: {$ec}; }\n\n";
			$css .= $this->build_root_vars_css( $accent );
			$css .= $this->build_chrome_css();
		}

		// ── Night mode (independent of the colour choice) ───────────────────────
		if ( (bool) get_user_meta( $user_id, CMB_Profile::META_NIGHT_MODE, true ) ) {
			$css .= $this->build_night_mode_css();
		}

		// Nothing to inject — skip the <style> tag entirely.
		if ( '' === $css ) {
			return;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		// Rationale: hex values are esc_attr()'d; chrome CSS is hardcoded constants.
		printf( "<style id=\"cmb-admin-vars\">\n%s</style>\n", $css );
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// ── Private helpers ────────────────────────────────────────────────────────

	/**
	 * Return the :root custom-property block for a concrete accent colour.
	 *
	 * Sets --cmb-accent + three darker tones + contrast, as well as all
	 * --wp-admin-theme-color family vars so the block editor and third-party
	 * code that reads those variables also adapts.
	 *
	 * The same variables are mirrored by applyLiveTheme() in JS so that
	 * updating them via element.style.setProperty() produces an instant preview.
	 *
	 * @since  1.0.0
	 * @param  string $accent Normalised #RRGGBB accent colour.
	 * @return string         :root { … } CSS block.
	 */
	private function build_root_vars_css( string $accent ): string {
		$d10      = CMB_Color::darken_hex( $accent, 10 );
		$d20      = CMB_Color::darken_hex( $accent, 20 );
		$d30      = CMB_Color::darken_hex( $accent, 30 );
		$bg       = CMB_Color::tint_hex( $accent, 10 );
		$rgb      = CMB_Color::hex_to_rgb( $accent );
		$d10_rgb  = CMB_Color::hex_to_rgb( $d10 );
		$d20_rgb  = CMB_Color::hex_to_rgb( $d20 );
		$contrast = CMB_Color::contrast_color( $accent );

		$a   = esc_attr( $accent );
		$e10 = esc_attr( $d10 );
		$e20 = esc_attr( $d20 );
		$e30 = esc_attr( $d30 );
		$ec  = esc_attr( $contrast );
		$ebg = esc_attr( $bg );

		return ":root {\n"
			. "\t--wp-admin-theme-color:               {$a};\n"
			. "\t--wp-admin-theme-color--rgb:           {$rgb};\n"
			. "\t--wp-admin-theme-color-darker-10:      {$e10};\n"
			. "\t--wp-admin-theme-color-darker-10--rgb: {$d10_rgb};\n"
			. "\t--wp-admin-theme-color-darker-20:      {$e20};\n"
			. "\t--wp-admin-theme-color-darker-20--rgb: {$d20_rgb};\n"
			. "\t--cmb-accent:          {$a};\n"
			. "\t--cmb-accent-d10:      {$e10};\n"
			. "\t--cmb-accent-d20:      {$e20};\n"
			. "\t--cmb-accent-d30:      {$e30};\n"
			. "\t--cmb-accent-contrast: {$ec};\n"
			. "\t--cmb-bg:              {$ebg};\n"
			. "}\n\n";
	}

	/**
	 * Return the admin chrome CSS rules.
	 *
	 * Every colour value is expressed as:
	 *   var(--cmb-accent, var(--wp-admin-theme-color, #2271b1))
	 *
	 * Cascade behaviour:
	 *  – Concrete colour saved in meta → --cmb-accent set in :root block above.
	 *  – JS live preview               → applyLiveTheme(hex) sets --cmb-accent
	 *                                    and all derived vars on :root inline
	 *                                    style → instant chrome update.
	 *  – JS revert to follow_wp        → clearLiveTheme() applies the native WP
	 *                                    colour captured in window._cmbNativeWpColor.
	 *
	 * These rules are only emitted for concrete colour choices; for "Follow
	 * WordPress" the method is never called and WP's own chrome is untouched.
	 * The final #2271b1 fallback covers ancient / custom colour schemes that
	 * predate the --wp-admin-theme-color custom property (pre WP 5.7).
	 *
	 * @since  1.0.0
	 * @return string CSS text.
	 */
	private function build_chrome_css(): string {
		// Shorthand fallback chains used throughout this method.
		// a   = accent
		// d10 = accent darkened 10 %
		// d20 = accent darkened 20 %
		// d30 = accent darkened 30 % (falls back to d20 for WP native – no WP var)
		$a   = 'var(--cmb-accent,     var(--wp-admin-theme-color,          #2271b1))';
		$d10 = 'var(--cmb-accent-d10, var(--wp-admin-theme-color-darker-10, #1a5c96))';
		$d20 = 'var(--cmb-accent-d20, var(--wp-admin-theme-color-darker-20, #155d8a))';
		$d30 = 'var(--cmb-accent-d30, var(--wp-admin-theme-color-darker-20, #155d8a))';
		$ec  = 'var(--cmb-accent-contrast, #ffffff)';
		$bg  = 'var(--cmb-bg, #f0f0f1)';

		$css = '';

		// ── Page background ───────────────────────────────────────────────────
		$css .= "/* --- Page background --- */\n"
			. "body.wp-admin, #wpwrap { background-color: {$bg}; }\n\n";

		// ── Sidebar menu ──────────────────────────────────────────────────────
		$css .= "/* --- Admin sidebar menu --- */\n"
			. "#adminmenuback, #adminmenuwrap, #adminmenu { background: {$a}; }\n\n"

			. "#adminmenu a { color: rgba(255,255,255,.85); }\n"
			. "#adminmenu a:hover { color: #fff; }\n"
			. "#adminmenu div.wp-menu-image:before { color: rgba(255,255,255,.6); }\n\n"

			. "#adminmenu li.menu-top:hover,\n"
			. "#adminmenu li.opensub > a.menu-top,\n"
			. "#adminmenu li > a.menu-top:focus { color: #fff; background: {$d10}; }\n"
			. "#adminmenu li.menu-top:hover div.wp-menu-image:before,\n"
			. "#adminmenu li.opensub > a.menu-top div.wp-menu-image:before,\n"
			. "#adminmenu li > a.menu-top:focus div.wp-menu-image:before { color: #fff; }\n\n"

			. "#adminmenu .wp-submenu,\n"
			. "#adminmenu .wp-has-current-submenu .wp-submenu,\n"
			. "#adminmenu .wp-has-current-submenu.opensub .wp-submenu { background: {$d20}; }\n"
			. "#adminmenu .wp-submenu a { color: rgba(255,255,255,.7); }\n"
			. "#adminmenu .wp-submenu a:focus,\n"
			. "#adminmenu .wp-submenu a:hover { color: #fff; }\n"
			. "#adminmenu .wp-has-current-submenu .wp-submenu .wp-submenu-head { color: rgba(255,255,255,.7); }\n\n"

			. "#adminmenu .current > a.menu-top,\n"
			. "#adminmenu .wp-has-current-submenu > a.menu-top { background: {$d30}; color: #fff; }\n"
			. "#adminmenu .current > .wp-menu-image:before,\n"
			. "#adminmenu .wp-has-current-submenu > .wp-menu-image:before { color: #fff; }\n"
			. "ul#adminmenu a.current { background: {$d30}; color: #fff; }\n"
			. "#adminmenu .current:after,\n"
			. "#adminmenu .wp-has-current-submenu:after { border-right-color: #f0f0f1; }\n\n";

		// ── Admin bar ─────────────────────────────────────────────────────────
		$css .= "/* --- Admin bar --- */\n"
			. "#wpadminbar { background: {$a}; }\n"
			. "#wpadminbar .ab-top-menu > li.hover > .ab-item,\n"
			. "#wpadminbar .ab-top-menu > li > .ab-item:focus,\n"
			. ".no-js #wpadminbar .ab-top-menu > li:hover > .ab-item,\n"
			. "#wpadminbar .ab-top-menu > li.ab-top-secondary.hover > .ab-item,\n"
			. "#wpadminbar .ab-top-menu > li.ab-top-secondary > .ab-item:focus,\n"
			. ".no-js #wpadminbar .ab-top-menu > li.ab-top-secondary:hover > .ab-item "
			. "{ background: {$d20}; color: #fff; }\n"
			. "#wpadminbar > #wp-toolbar > #wp-admin-bar-root-default li a,\n"
			. "#wpadminbar .ab-item,\n"
			. "#wpadminbar .ab-item:hover { color: rgba(255,255,255,.85); }\n\n";

		// ── Primary buttons ───────────────────────────────────────────────────
		$css .= "/* --- Primary buttons --- */\n"
			. ".wp-core-ui .button-primary {\n"
			. "\tbackground:   {$a};\n"
			. "\tborder-color: {$d10};\n"
			. "\tcolor:        {$ec};\n"
			. "\tbox-shadow:   0 1px 0 {$d10};\n"
			. "\ttext-shadow:  none;\n"
			. "}\n"
			. ".wp-core-ui .button-primary:hover,\n"
			. ".wp-core-ui .button-primary:focus {\n"
			. "\tbackground:   {$d10};\n"
			. "\tborder-color: {$d20};\n"
			. "\tcolor:        {$ec};\n"
			. "\tbox-shadow:   0 1px 0 {$d20};\n"
			. "}\n"
			. ".wp-core-ui .button-primary:active {\n"
			. "\tbackground:   {$d20};\n"
			. "\tborder-color: {$d20};\n"
			. "\tbox-shadow:   inset 0 2px 5px -3px {$d30};\n"
			. "\tcolor:        {$ec};\n"
			. "}\n"
			. ".wp-core-ui .button-primary.button-hero { background: {$a}; border-color: {$d10}; }\n\n";

		// ── Content-area links ────────────────────────────────────────────────
		$css .= "/* --- Content-area links --- */\n"
			. "#wpcontent a, #wpcontent h2 a, #wpcontent h3 a,\n"
			. "#wpbody-content a { color: {$a}; }\n"
			. "#wpcontent a:hover,\n"
			. "#wpbody-content a:hover { color: {$d20}; }\n\n";

		// ── Focus ring ────────────────────────────────────────────────────────
		$css .= "/* --- Focus ring --- */\n"
			. ":focus-visible { box-shadow: 0 0 0 2px #fff, 0 0 0 4px {$a}; outline: none; }\n";

		return $css;
	}

	/**
	 * Return the night-mode CSS rules.
	 *
	 * Applies `filter: invert(1) hue-rotate(180deg)` to `html` (not `body`) so
	 * that `position:fixed` elements (e.g. #wpadminbar) keep the viewport as
	 * their containing block.  If the filter were on `body`, body's margin-top
	 * (32 px in WP when the toolbar is visible) would shift fixed children down.
	 *
	 * The hue-rotate(180deg) keeps blues blue and greens green (round-trips the
	 * hue wheel).  Media elements that must render naturally (images, video,
	 * iframes, canvas, and the Iris colour-picker wheel) are re-inverted with a
	 * second identical filter rule so they cancel out.
	 *
	 * @since  1.0.0
	 * @return string CSS text.
	 */
	private function build_night_mode_css(): string {
		return "/* --- Night mode --- */\n"
			. "html { filter: invert(1) hue-rotate(180deg); }\n"
			. "body img,\n"
			. "body video,\n"
			. "body iframe,\n"
			. "body canvas,\n"
			. "body .iris-picker { filter: invert(1) hue-rotate(180deg); }\n";
	}
}
