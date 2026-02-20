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
 * Night mode (explicit dark colour overrides, no filter) is orthogonal to the
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

		// WCAG-compliant contrast colors for each tone.
		$contrast     = CMB_Color::contrast_color( $accent );
		$contrast_d10 = CMB_Color::contrast_color( $d10 );
		$contrast_d20 = CMB_Color::contrast_color( $d20 );
		$contrast_d30 = CMB_Color::contrast_color( $d30 );

		$a   = esc_attr( $accent );
		$e10 = esc_attr( $d10 );
		$e20 = esc_attr( $d20 );
		$e30 = esc_attr( $d30 );
		$ec  = esc_attr( $contrast );
		$ec10 = esc_attr( $contrast_d10 );
		$ec20 = esc_attr( $contrast_d20 );
		$ec30 = esc_attr( $contrast_d30 );
		$ebg = esc_attr( $bg );

		return ":root {\n"
			. "\t--wp-admin-theme-color:               {$a};\n"
			. "\t--wp-admin-theme-color--rgb:           {$rgb};\n"
			. "\t--wp-admin-theme-color-darker-10:      {$e10};\n"
			. "\t--wp-admin-theme-color-darker-10--rgb: {$d10_rgb};\n"
			. "\t--wp-admin-theme-color-darker-20:      {$e20};\n"
			. "\t--wp-admin-theme-color-darker-20--rgb: {$d20_rgb};\n"
			. "\t--cmb-accent:              {$a};\n"
			. "\t--cmb-accent-d10:          {$e10};\n"
			. "\t--cmb-accent-d20:          {$e20};\n"
			. "\t--cmb-accent-d30:          {$e30};\n"
			. "\t--cmb-accent-contrast:     {$ec};\n"
			. "\t--cmb-accent-d10-contrast: {$ec10};\n"
			. "\t--cmb-accent-d20-contrast: {$ec20};\n"
			. "\t--cmb-accent-d30-contrast: {$ec30};\n"
			. "\t--cmb-bg:                  {$ebg};\n"
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
		// ec* = WCAG contrast color for each tone (black or white depending on luminance)
		$a    = 'var(--cmb-accent,     var(--wp-admin-theme-color,          #2271b1))';
		$d10  = 'var(--cmb-accent-d10, var(--wp-admin-theme-color-darker-10, #1a5c96))';
		$d20  = 'var(--cmb-accent-d20, var(--wp-admin-theme-color-darker-20, #155d8a))';
		$d30  = 'var(--cmb-accent-d30, var(--wp-admin-theme-color-darker-20, #155d8a))';
		$ec   = 'var(--cmb-accent-contrast, #ffffff)';
		$ec10 = 'var(--cmb-accent-d10-contrast, #ffffff)';
		$ec20 = 'var(--cmb-accent-d20-contrast, #ffffff)';
		$ec30 = 'var(--cmb-accent-d30-contrast, #ffffff)';
		$bg   = 'var(--cmb-bg, #f0f0f1)';

		$css = '';

		// ── Page background ───────────────────────────────────────────────────
		$css .= "/* --- Page background --- */\n"
			. "body.wp-admin, #wpwrap { background-color: {$bg}; }\n\n";

		// ── Sidebar menu ──────────────────────────────────────────────────────
		// Text colors use WCAG-computed contrast vars for accessibility.
		$css .= "/* --- Admin sidebar menu (WCAG contrast) --- */\n"
			. "#adminmenuback, #adminmenuwrap, #adminmenu { background: {$a}; }\n\n"

			. "#adminmenu a { color: {$ec}; opacity: 0.85; }\n"
			. "#adminmenu a:hover { color: {$ec}; opacity: 1; }\n"
			. "#adminmenu div.wp-menu-image:before { color: {$ec}; opacity: 0.6; }\n"
			. "#adminmenu .collapse-button-label { color: {$ec}; }\n"
			. "#collapse-button { color: {$ec}; }\n"
			. "#collapse-button .collapse-button-icon { fill: {$ec}; }\n\n"

			. "#adminmenu li.menu-top:hover,\n"
			. "#adminmenu li.opensub > a.menu-top,\n"
			. "#adminmenu li > a.menu-top:focus { color: {$ec10}; opacity: 1; background: {$d10}; }\n"
			. "#adminmenu li.menu-top:hover div.wp-menu-image:before,\n"
			. "#adminmenu li.opensub > a.menu-top div.wp-menu-image:before,\n"
			. "#adminmenu li > a.menu-top:focus div.wp-menu-image:before { color: {$ec10}; opacity: 1; }\n\n"

			. "#adminmenu .wp-submenu,\n"
			. "#adminmenu .wp-has-current-submenu .wp-submenu,\n"
			. "#adminmenu .wp-has-current-submenu.opensub .wp-submenu { background: {$d20}; }\n"
			. "#adminmenu .wp-submenu a { color: {$ec20}; opacity: 0.85; }\n"
			. "#adminmenu .wp-submenu a:focus,\n"
			. "#adminmenu .wp-submenu a:hover { color: {$ec20}; opacity: 1; }\n"
			. "#adminmenu .wp-has-current-submenu .wp-submenu .wp-submenu-head { color: {$ec20}; opacity: 0.85; }\n\n"

			. "#adminmenu .current > a.menu-top,\n"
			. "#adminmenu .wp-has-current-submenu > a.menu-top { background: {$d30}; color: {$ec30}; opacity: 1; }\n"
			. "#adminmenu .current > .wp-menu-image:before,\n"
			. "#adminmenu .wp-has-current-submenu > .wp-menu-image:before { color: {$ec30}; opacity: 1; }\n"
			. "ul#adminmenu a.current { background: {$d30}; color: {$ec30}; opacity: 1; }\n"
			. "#adminmenu .current:after,\n"
			. "#adminmenu .wp-has-current-submenu:after { border-right-color: {$bg}; }\n\n";

		// ── Admin bar ─────────────────────────────────────────────────────────
		// Text colors use WCAG-computed contrast vars for accessibility.
		$css .= "/* --- Admin bar (WCAG contrast) --- */\n"
			. "#wpadminbar { background: {$a}; }\n"
			// Hover/focus states on top-level items.
			. "#wpadminbar .ab-top-menu > li.hover > .ab-item,\n"
			. "#wpadminbar .ab-top-menu > li > .ab-item:focus,\n"
			. ".no-js #wpadminbar .ab-top-menu > li:hover > .ab-item,\n"
			. "#wpadminbar .ab-top-menu > li.ab-top-secondary.hover > .ab-item,\n"
			. "#wpadminbar .ab-top-menu > li.ab-top-secondary > .ab-item:focus,\n"
			. ".no-js #wpadminbar .ab-top-menu > li.ab-top-secondary:hover > .ab-item "
			. "{ background: {$d20}; color: {$ec20}; }\n"
			// All admin bar text elements use WCAG contrast (comprehensive).
			. "#wpadminbar,\n"
			. "#wpadminbar a,\n"
			. "#wpadminbar .ab-item,\n"
			. "#wpadminbar .ab-label,\n"
			. "#wpadminbar .ab-empty-item,\n"
			. "#wpadminbar .quicklinks .ab-empty-item,\n"
			. "#wpadminbar > #wp-toolbar span,\n"
			. "#wpadminbar > #wp-toolbar > #wp-admin-bar-root-default li a,\n"
			. "#wpadminbar #wp-admin-bar-site-name > .ab-item,\n"
			. "#wpadminbar #wp-admin-bar-my-account > .ab-item { color: {$ec}; }\n"
			// Hover states.
			. "#wpadminbar a:hover,\n"
			. "#wpadminbar .ab-item:hover,\n"
			. "#wpadminbar .ab-label:hover { color: {$ec}; }\n"
			// .ab-label in various contexts (My Account display name, etc).
			. "#wpadminbar .ab-label,\n"
			. "#wpadminbar #wp-admin-bar-my-account .ab-label,\n"
			. "#wpadminbar .ab-top-menu > li > .ab-item > .ab-label,\n"
			. "#wpadminbar .display-name { color: {$ec}; }\n"
			// Icons in admin bar (dashicons, SVG, etc).
			. "#wpadminbar .ab-icon,\n"
			. "#wpadminbar .ab-icon:before,\n"
			. "#wpadminbar .ab-item .ab-icon:before,\n"
			. "#wpadminbar #adminbarsearch:before,\n"
			. "#wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon:before,\n"
			. "#wpadminbar .quicklinks li a:before { color: {$ec}; }\n"
			// WP logo specific.
			. "#wpadminbar #wp-admin-bar-wp-logo > .ab-item { color: {$ec}; }\n"
			. "#wpadminbar #wp-admin-bar-wp-logo:hover > .ab-item { background: {$d20}; color: {$ec20}; }\n"
			// Submenus use darkened background with appropriate contrast.
			. "#wpadminbar .ab-submenu,\n"
			. "#wpadminbar .ab-sub-wrapper,\n"
			. "#wpadminbar ul.ab-submenu,\n"
			. "#wpadminbar .quicklinks .menupop ul.ab-sub-secondary { background: {$d20}; }\n"
			. "#wpadminbar .ab-submenu .ab-item,\n"
			. "#wpadminbar .ab-sub-wrapper .ab-item,\n"
			. "#wpadminbar .ab-submenu .ab-label,\n"
			. "#wpadminbar .ab-sub-wrapper .ab-label,\n"
			. "#wpadminbar .ab-submenu a,\n"
			. "#wpadminbar .ab-sub-wrapper a { color: {$ec20}; }\n"
			. "#wpadminbar .ab-submenu .ab-item:hover,\n"
			. "#wpadminbar .ab-sub-wrapper .ab-item:hover,\n"
			. "#wpadminbar .ab-submenu .ab-item:focus,\n"
			. "#wpadminbar .ab-sub-wrapper .ab-item:focus,\n"
			. "#wpadminbar .ab-submenu a:hover,\n"
			. "#wpadminbar .ab-sub-wrapper a:hover { color: {$ec20}; background: {$d30}; }\n"
			// Account/avatar area.
			. "#wpadminbar #wp-admin-bar-my-account.with-avatar > a img { border-color: {$ec}; }\n\n";

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
	 * Uses explicit dark colour overrides only — no `filter` property.
	 * `filter` always creates a new stacking context and makes any filtered
	 * ancestor the containing block for `position:fixed` descendants.  In WP
	 * admin `#adminmenuback` and `#adminmenuwrap` are `position:fixed` inside
	 * `#wpwrap`, so filtering any ancestor (html, body, or #wpwrap) re-parents
	 * those elements and causes `#wpcontent` to paint over the sidebar —
	 * making admin menu items unresponsive to clicks.
	 *
	 * Instead, a dark colour palette is defined as CSS custom properties and
	 * applied directly to the key WP admin elements.  Because only `color`,
	 * `background-color`, and `border-color` are changed, no stacking contexts
	 * are created and event handling is completely unaffected.
	 *
	 * @since  1.0.0
	 * @return string CSS text.
	 */
	private function build_night_mode_css(): string {
		return "/* --- Night mode: direct colour overrides, no filter --- */\n"

		// ── Dark palette variables ─────────────────────────────────────────
		. ":root {\n"
		. "\t--cmb-nm-bg:      #1d1d1d;\n"
		. "\t--cmb-nm-surface: #2c2c2c;\n"
		. "\t--cmb-nm-raised:  #383838;\n"
		. "\t--cmb-nm-text:    #e4e4e4;\n"
		. "\t--cmb-nm-muted:   #9a9a9a;\n"
		. "\t--cmb-nm-border:  #4a4a4a;\n"
		. "\t--cmb-nm-input:   #363636;\n"
		. "}\n\n"

		// ── Body + main content wrappers ───────────────────────────────────
		. "body.wp-admin,\n"
		. "#wpcontent,\n"
		. "#wpbody,\n"
		. "#wpbody-content { background-color: var(--cmb-nm-bg); color: var(--cmb-nm-text); }\n\n"

		// ── Admin sidebar menu ─────────────────────────────────────────────
		. "/* --- Admin sidebar menu (night mode) --- */\n"
		. "#adminmenuback, #adminmenuwrap, #adminmenu { background: var(--cmb-nm-surface); }\n\n"
		. "#adminmenu li.menu-top:hover,\n"
		. "#adminmenu li.opensub > a.menu-top,\n"
		. "#adminmenu li > a.menu-top:focus { color: #fff; background: var(--cmb-nm-raised); }\n\n"
		. "#adminmenu .wp-submenu,\n"
		. "#adminmenu .wp-has-current-submenu .wp-submenu,\n"
		. "#adminmenu .wp-has-current-submenu.opensub .wp-submenu { background: var(--cmb-nm-bg); }\n\n"
		. "#adminmenu .current > a.menu-top,\n"
		. "#adminmenu .wp-has-current-submenu > a.menu-top { background: var(--cmb-nm-raised); color: #fff; }\n"
		. "ul#adminmenu a.current { background: var(--cmb-nm-raised); }\n"
		. "#adminmenu .current:after,\n"
		. "#adminmenu .wp-has-current-submenu:after { border-right-color: var(--cmb-nm-bg); }\n\n"

		// ── Admin bar ─────────────────────────────────────────────────────
		. "/* --- Admin bar (night mode) --- */\n"
		. "#wpadminbar { background: var(--cmb-nm-surface); }\n"
		. "#wpadminbar .ab-top-menu > li.hover > .ab-item,\n"
		. "#wpadminbar .ab-top-menu > li > .ab-item:focus,\n"
		. ".no-js #wpadminbar .ab-top-menu > li:hover > .ab-item,\n"
		. "#wpadminbar .ab-top-menu > li.ab-top-secondary.hover > .ab-item,\n"
		. "#wpadminbar .ab-top-menu > li.ab-top-secondary > .ab-item:focus,\n"
		. ".no-js #wpadminbar .ab-top-menu > li.ab-top-secondary:hover > .ab-item "
		. "{ background: var(--cmb-nm-raised); }\n\n"

		// ── Headings ───────────────────────────────────────────────────────
		. "#wpbody-content h1, #wpbody-content h2, #wpbody-content h3,\n"
		. "#wpbody-content h4, #wpbody-content h5, #wpbody-content h6 { color: var(--cmb-nm-text); }\n\n"

		// ── Labels + descriptions ──────────────────────────────────────────
		. "#wpbody-content label { color: var(--cmb-nm-text); }\n"
		. "#wpbody-content .description,\n"
		. "#wpbody-content .howto { color: var(--cmb-nm-muted); }\n"
		. ".form-table th, .form-table td { color: var(--cmb-nm-text); }\n\n"

		// ── Form inputs ────────────────────────────────────────────────────
		. "input[type=\"text\"], input[type=\"email\"], input[type=\"url\"],\n"
		. "input[type=\"password\"], input[type=\"number\"], input[type=\"search\"],\n"
		. "input[type=\"tel\"], input[type=\"date\"], input[type=\"datetime-local\"],\n"
		. "textarea, select {\n"
		. "\tbackground-color: var(--cmb-nm-input);\n"
		. "\tborder-color:     var(--cmb-nm-border);\n"
		. "\tcolor:            var(--cmb-nm-text);\n"
		. "}\n"
		. "input::placeholder, textarea::placeholder { color: var(--cmb-nm-muted); }\n\n"

		// ── Secondary buttons ──────────────────────────────────────────────
		. ".wp-core-ui .button,\n"
		. ".wp-core-ui .button-secondary {\n"
		. "\tbackground-color: var(--cmb-nm-raised);\n"
		. "\tborder-color:     var(--cmb-nm-border);\n"
		. "\tcolor:            var(--cmb-nm-text);\n"
		. "}\n"
		. ".wp-core-ui .button:hover,\n"
		. ".wp-core-ui .button-secondary:hover {\n"
		. "\tbackground-color: var(--cmb-nm-surface);\n"
		. "\tborder-color:     var(--cmb-nm-muted);\n"
		. "\tcolor:            var(--cmb-nm-text);\n"
		. "}\n\n"

		// ── Admin notices ──────────────────────────────────────────────────
		. ".notice, div.updated, div.error, div.settings-error {\n"
		. "\tbackground-color: var(--cmb-nm-surface);\n"
		. "\tborder-color:     var(--cmb-nm-border);\n"
		. "\tcolor:            var(--cmb-nm-text);\n"
		. "}\n"
		. ".notice p, div.updated p, div.error p { color: var(--cmb-nm-text); }\n\n"

		// ── WP list tables ─────────────────────────────────────────────────
		. "#wpbody-content .wp-list-table td,\n"
		. "#wpbody-content .wp-list-table th {\n"
		. "\tbackground-color: var(--cmb-nm-surface);\n"
		. "\tborder-color:     var(--cmb-nm-border);\n"
		. "\tcolor:            var(--cmb-nm-text);\n"
		. "}\n"
		. "#wpbody-content .wp-list-table .alternate td,\n"
		. "#wpbody-content .wp-list-table .alternate th { background-color: var(--cmb-nm-bg); }\n"
		. "#wpbody-content .wp-list-table thead td,\n"
		. "#wpbody-content .wp-list-table thead th { background-color: var(--cmb-nm-raised); }\n\n"

		// ── Metaboxes / postboxes ──────────────────────────────────────────
		. ".postbox {\n"
		. "\tbackground-color: var(--cmb-nm-surface);\n"
		. "\tborder-color:     var(--cmb-nm-border);\n"
		. "}\n"
		. ".postbox .inside { background-color: var(--cmb-nm-surface); }\n"
		. ".postbox h2.hndle, .postbox .hndle, .postbox-header {\n"
		. "\tborder-color: var(--cmb-nm-border);\n"
		. "\tcolor:        var(--cmb-nm-text);\n"
		. "}\n\n"

		// ── Cards ──────────────────────────────────────────────────────────
		. ".card {\n"
		. "\tbackground-color: var(--cmb-nm-surface);\n"
		. "\tborder-color:     var(--cmb-nm-border);\n"
		. "\tbox-shadow:       none;\n"
		. "}\n\n"

		// ── Screen options / help ──────────────────────────────────────────
		. "#screen-meta {\n"
		. "\tbackground-color: var(--cmb-nm-surface);\n"
		. "\tborder-color:     var(--cmb-nm-border);\n"
		. "\tcolor:            var(--cmb-nm-text);\n"
		. "}\n"
		. "#screen-meta-links .show-settings {\n"
		. "\tbackground-color: var(--cmb-nm-raised);\n"
		. "\tborder-color:     var(--cmb-nm-border);\n"
		. "\tcolor:            var(--cmb-nm-text);\n"
		. "}\n\n"

		// ── Footer ─────────────────────────────────────────────────────────
		. "#wpfooter {\n"
		. "\tbackground-color: var(--cmb-nm-bg);\n"
		. "\tborder-top-color: var(--cmb-nm-border);\n"
		. "\tcolor:            var(--cmb-nm-muted);\n"
		. "}\n";
	}
}
