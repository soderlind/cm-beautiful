/* global wp, jQuery */
/**
 * Color Me Beautiful – Profile Color Picker + Live Admin Preview
 *
 * Initialises the WP Iris color picker, keeps the small swatches updated,
 * and drives an instant live preview of the chosen accent colour across the
 * entire WP admin chrome by updating CSS custom properties on :root.
 *
 * PHP emits admin chrome CSS rules only when a concrete accent colour is
 * resolved; for "Follow WordPress" no chrome override is emitted and WP
 * manages its own admin chrome.  When a concrete accent IS active, every
 * chrome rule is expressed as:
 *   background: var(--cmb-accent, var(--wp-admin-theme-color, #2271b1))
 *
 * So we only need to set --cmb-accent (plus its derived tones) on
 * document.documentElement.style to get an immediate, page-reload-free update
 * of the sidebar menu, admin bar, buttons, links, and focus rings.
 *
 * "Follow WordPress" live preview: clearLiveTheme() reads window._cmbNativeWpColor
 * and calls applyLiveTheme() with that value, restoring the active WP colour
 * scheme instantly.  window._cmbNativeWpColor is provided by an inline <script>
 * at admin_head priority 1 (before our :root override) so we always know the
 * true native colour even when a concrete preset was saved as the current value.
 *
 * @package cm-beautiful
 */
( function ( $, wp ) {
	'use strict';

	/* -------------------------------------------------------------------------
	 * Colour utilities  (JS mirrors of CMB_Color PHP methods)
	 * ---------------------------------------------------------------------- */

	/**
	 * Normalise a hex string to 7-char lowercase #rrggbb.
	 * Returns '' for any invalid input.
	 *
	 * @param  {string} hex
	 * @return {string}
	 */
	function normalizeHex( hex ) {
		if ( ! hex || ! /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test( hex ) ) {
			return '';
		}
		hex = hex.replace( '#', '' );
		if ( 3 === hex.length ) {
			hex = hex[ 0 ] + hex[ 0 ] + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ];
		}
		return '#' + hex.toLowerCase();
	}

	/**
	 * Darken a hex colour by scaling each RGB channel toward black.
	 * Mirrors CMB_Color::darken_hex().
	 *
	 * @param  {string} hex     #rrggbb source colour.
	 * @param  {number} percent 0–100. 10 → channels × 0.9.
	 * @return {string}         Darkened #rrggbb, or '' on invalid input.
	 */
	function darkenHex( hex, percent ) {
		hex = normalizeHex( hex );
		if ( ! hex ) { return ''; }

		var factor = 1 - percent / 100;
		var r = Math.round( parseInt( hex.slice( 1, 3 ), 16 ) * factor );
		var g = Math.round( parseInt( hex.slice( 3, 5 ), 16 ) * factor );
		var b = Math.round( parseInt( hex.slice( 5, 7 ), 16 ) * factor );

		r = Math.max( 0, Math.min( 255, r ) );
		g = Math.max( 0, Math.min( 255, g ) );
		b = Math.max( 0, Math.min( 255, b ) );

		return '#' + [ r, g, b ].map( function ( v ) {
			return v.toString( 16 ).padStart( 2, '0' );
		} ).join( '' );
	}

	/**
	 * Tint a hex colour toward white by blending it with #ffffff.
	 * Mirrors CMB_Color::tint_hex().
	 *
	 * @param  {string} hex     #rrggbb source colour.
	 * @param  {number} percent 0–100. 5 → 5 % accent + 95 % white.
	 * @return {string}         Tinted #rrggbb, or '#f0f0f1' on invalid input.
	 */
	function tintHex( hex, percent ) {
		hex = normalizeHex( hex );
		if ( ! hex ) { return '#f0f0f1'; }

		var factor = percent / 100;
		var r = Math.round( 255 * ( 1 - factor ) + parseInt( hex.slice( 1, 3 ), 16 ) * factor );
		var g = Math.round( 255 * ( 1 - factor ) + parseInt( hex.slice( 3, 5 ), 16 ) * factor );
		var b = Math.round( 255 * ( 1 - factor ) + parseInt( hex.slice( 5, 7 ), 16 ) * factor );

		r = Math.max( 0, Math.min( 255, r ) );
		g = Math.max( 0, Math.min( 255, g ) );
		b = Math.max( 0, Math.min( 255, b ) );

		return '#' + [ r, g, b ].map( function ( v ) {
			return v.toString( 16 ).padStart( 2, '0' );
		} ).join( '' );
	}

	/**
	 * Return "R, G, B" for use in CSS rgb() / rgba() or --color--rgb vars.
	 *
	 * @param  {string} hex
	 * @return {string}  e.g. "79, 70, 229", or '' on invalid.
	 */
	function hexToRgb( hex ) {
		hex = normalizeHex( hex );
		if ( ! hex ) { return ''; }
		return [
			parseInt( hex.slice( 1, 3 ), 16 ),
			parseInt( hex.slice( 3, 5 ), 16 ),
			parseInt( hex.slice( 5, 7 ), 16 ),
		].join( ', ' );
	}

	/**
	 * WCAG 2.1 relative-luminance contrast colour.
	 * Mirrors CMB_Color::contrast_color(); uses threshold 0.179.
	 *
	 * @param  {string} hex
	 * @return {string}  '#000000' (light bg) or '#ffffff' (dark bg).
	 */
	function contrastColor( hex ) {
		hex = normalizeHex( hex );
		if ( ! hex ) { return '#ffffff'; }

		var linearise = function ( c ) {
			c /= 255;
			return c <= 0.04045
				? c / 12.92
				: Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
		};

		var r = linearise( parseInt( hex.slice( 1, 3 ), 16 ) );
		var g = linearise( parseInt( hex.slice( 3, 5 ), 16 ) );
		var b = linearise( parseInt( hex.slice( 5, 7 ), 16 ) );

		return ( 0.2126 * r + 0.7152 * g + 0.0722 * b ) > 0.179
			? '#000000'
			: '#ffffff';
	}

	/* -------------------------------------------------------------------------
	 * Live theme update
	 * ---------------------------------------------------------------------- */

	var root = document.documentElement;

	/**
	 * Push a concrete accent colour into the CSS custom properties on :root.
	 *
	 * Because every admin chrome CSS rule is written as
	 *   property: var(--cmb-accent, var(--wp-admin-theme-color, …))
	 * updating these properties alone is sufficient to repaint the entire
	 * WP admin sidebar, admin bar, primary buttons, links, and focus rings —
	 * no page reload, no CSS string rebuild.
	 *
	 * @param {string} hex  Accent colour (#rrggbb or #rgb).
	 */
	function applyLiveTheme( hex ) {
		hex = normalizeHex( hex );
		if ( ! hex ) { return; }

		var d10     = darkenHex( hex, 10 );
		var d20     = darkenHex( hex, 20 );
		var d30     = darkenHex( hex, 30 );
		var bg      = tintHex( hex, 10 );
		var rgb     = hexToRgb( hex );
		var d10rgb  = hexToRgb( d10 );
		var d20rgb  = hexToRgb( d20 );

		// WCAG-compliant contrast colors for each tone.
		var contrast    = contrastColor( hex );
		var contrastD10 = contrastColor( d10 );
		var contrastD20 = contrastColor( d20 );
		var contrastD30 = contrastColor( d30 );

		// Plugin vars — consumed by every admin chrome rule via var(--cmb-accent…).
		root.style.setProperty( '--cmb-accent',              hex );
		root.style.setProperty( '--cmb-accent-d10',          d10 );
		root.style.setProperty( '--cmb-accent-d20',          d20 );
		root.style.setProperty( '--cmb-accent-d30',          d30 );
		root.style.setProperty( '--cmb-accent-contrast',     contrast );
		root.style.setProperty( '--cmb-accent-d10-contrast', contrastD10 );
		root.style.setProperty( '--cmb-accent-d20-contrast', contrastD20 );
		root.style.setProperty( '--cmb-accent-d30-contrast', contrastD30 );
		root.style.setProperty( '--cmb-bg',                  bg );

		// WP native vars — picked up by block editor + third-party code.
		root.style.setProperty( '--wp-admin-theme-color',               hex );
		root.style.setProperty( '--wp-admin-theme-color--rgb',          rgb );
		root.style.setProperty( '--wp-admin-theme-color-darker-10',     d10 );
		root.style.setProperty( '--wp-admin-theme-color-darker-10--rgb', d10rgb );
		root.style.setProperty( '--wp-admin-theme-color-darker-20',     d20 );
		root.style.setProperty( '--wp-admin-theme-color-darker-20--rgb', d20rgb );
	}

	/**
	 * Revert to "Follow WordPress" in the live preview.
	 *
	 * Applies the native WP colour (captured before any PHP override runs)
	 * via applyLiveTheme() so all CSS vars are set to consistent values.
	 * This avoids relying on the 'initial' keyword for custom properties,
	 * which has known browser quirks.
	 *
	 * @see inject_native_color_capture() in class-cmb-admin-theme.php
	 */
	function clearLiveTheme() {
		var native = ( window._cmbNativeWpColor || '' );
		var nativeHex = normalizeHex( native ) || '#2271b1';
		applyLiveTheme( nativeHex );
	}

	/* -------------------------------------------------------------------------
	 * DOM-ready
	 * ---------------------------------------------------------------------- */

	$( function () {
		var $presetSelect = $( '#cmb_ui_preset' );
		var $presetSwatch = $( '#cmb-preset-swatch' );
		var $colorInput   = $( '#cmb_ui_custom_accent' );
		var $customSwatch = $( '#cmb-custom-swatch' );

		// ------------------------------------------------------------------
		// Helpers
		// ------------------------------------------------------------------

		/** Update the small swatch next to the preset dropdown. */
		function updatePresetSwatch() {
			var hex = $presetSelect.find( ':selected' ).data( 'hex' ) || '';
			$presetSwatch.css( 'background-color', hex );
		}

		/**
		 * Resolve the effective accent for the live preview based on the
		 * current form state: custom picker first, then preset, then null
		 * when "Follow WordPress" is selected and no custom colour is set.
		 *
		 * @return {string|null}
		 */
		function resolveCurrentAccent() {
			var custom = normalizeHex( $colorInput.val() );
			if ( custom ) { return custom; }

			var presetHex = $presetSelect.find( ':selected' ).data( 'hex' ) || '';
			return normalizeHex( presetHex ) || null;
		}

		/** Apply or clear the live theme based on the current form state. */
		function updateLiveTheme() {
			// "Follow WordPress" always clears the override, regardless of whether
			// a value is still present in the custom accent picker.
			if ( 'follow_wp' === $presetSelect.val() ) {
				clearLiveTheme();
				return;
			}
			var hex = resolveCurrentAccent();
			if ( hex ) {
				applyLiveTheme( hex );
			} else {
				clearLiveTheme();
			}
		}

		// ------------------------------------------------------------------
		// Preset dropdown
		// ------------------------------------------------------------------

		$presetSelect.on( 'change', function () {
			updatePresetSwatch();
			updateLiveTheme();
		} );

		// Seed swatch on page load (reflects saved value without forcing a
		// redundant live-theme call — PHP already injected the correct CSS).
		updatePresetSwatch();

		// ------------------------------------------------------------------
		// WP Color Picker (Iris)
		// ------------------------------------------------------------------

		$colorInput.wpColorPicker( {
			/**
			 * Fired on every colour change (drag, text entry, paste).
			 * ui.color is an Iris Color instance; .toString() returns #rrggbb.
			 *
			 * Guard against follow_wp being selected: Iris may fire `change`
			 * on initialisation (or when the field is focused) before the user
			 * has interacted with it, which would incorrectly override the
			 * follow_wp live preview with the stale custom-accent value.
			 */
			change: function ( _event, ui ) {
				if ( 'follow_wp' === $presetSelect.val() ) {
					return;
				}
				var hex = normalizeHex( ui.color.toString() );
				$customSwatch.css( 'background-color', hex );
				applyLiveTheme( hex );
			},

			/**
			 * Fired when the "Clear" button is clicked.
			 * 'change' is NOT dispatched in this case — handle separately.
			 */
			clear: function () {
				$customSwatch.css( 'background-color', '' );
				// Fall back to the preset (or follow_wp if that is selected).
				updateLiveTheme();
			},
		} );

		// Seed the custom swatch from the already-saved value.
		var initialColor = normalizeHex( $colorInput.val() );
		if ( initialColor ) {
			$customSwatch.css( 'background-color', initialColor );
		}

		// ------------------------------------------------------------------
		// Night mode toggle
		// ------------------------------------------------------------------

		var $nightToggle = $( '#cmb_ui_night_mode' );

		/** Add or remove the night-mode filter class on body. */
		function applyNightMode( on ) {
			if ( on ) {
				$( 'body' ).addClass( 'cmb-night-mode' );
			} else {
				$( 'body' ).removeClass( 'cmb-night-mode' );
			}
		}

		$nightToggle.on( 'change', function () {
			applyNightMode( this.checked );
		} );

		// Seed on page load (reflects saved state without waiting for a save).
		applyNightMode( $nightToggle.is( ':checked' ) );
	} );

} )( jQuery, wp );
