<?php
/**
 * Color Me Beautiful – Color Utility
 *
 * Static helper class for hex colour validation, normalisation, and
 * WCAG-compliant contrast colour computation.
 *
 * @package    cm-beautiful
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides static colour utilities used throughout the plugin.
 *
 * @since 1.0.0
 */
final class CMB_Color {

	/**
	 * No instances needed – all methods are static.
	 */
	private function __construct() {}

	/**
	 * Check whether a string is a valid CSS hex colour (#RGB or #RRGGBB).
	 *
	 * @since  1.0.0
	 * @param  string $hex Candidate colour string, including the leading #.
	 * @return bool        True when the string is a valid 3- or 6-digit hex colour.
	 */
	public static function is_valid_hex( string $hex ): bool {
		return (bool) preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex );
	}

	/**
	 * Expand a #RGB shorthand to full #RRGGBB notation.
	 *
	 * A 6-digit hex colour is returned unchanged (lowercased and with # prefix).
	 * An invalid string returns an empty string.
	 *
	 * @since  1.0.0
	 * @param  string $hex Hex colour with leading #.
	 * @return string      Normalised 7-character #RRGGBB string, or '' on invalid input.
	 */
	public static function normalize_hex( string $hex ): string {
		if ( ! self::is_valid_hex( $hex ) ) {
			return '';
		}

		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			// Double each nibble independently: #abc → #aabbcc.
			$hex = $hex[ 0 ] . $hex[ 0 ] . $hex[ 1 ] . $hex[ 1 ] . $hex[ 2 ] . $hex[ 2 ];
		}

		return '#' . strtolower( $hex );
	}

	/**
	 * Darken a hex colour by scaling each RGB channel toward black.
	 *
	 * A $percent of 10 reduces every channel to 90 % of its original value.
	 * Returns an empty string for invalid input.
	 *
	 * @since  1.0.0
	 * @param  string $hex     Source hex colour with leading #.
	 * @param  float  $percent Percentage to darken (0–100).
	 * @return string          Darkened #RRGGBB colour, or '' on invalid input.
	 */
	public static function darken_hex( string $hex, float $percent ): string {
		$hex = self::normalize_hex( $hex );

		if ( '' === $hex ) {
			return '';
		}

		$factor = 1.0 - ( $percent / 100.0 );

		$r = (int) round( hexdec( substr( $hex, 1, 2 ) ) * $factor );
		$g = (int) round( hexdec( substr( $hex, 3, 2 ) ) * $factor );
		$b = (int) round( hexdec( substr( $hex, 5, 2 ) ) * $factor );

		return sprintf(
			'#%02x%02x%02x',
			max( 0, min( 255, $r ) ),
			max( 0, min( 255, $g ) ),
			max( 0, min( 255, $b ) )
		);
	}

	/**
	 * Tint a hex colour toward white by blending it with #ffffff.
	 *
	 * A $percent of 5 returns a colour that is 5 % accent + 95 % white,
	 * producing a very light, near-white tint suitable for page backgrounds.
	 * Returns '#f0f0f1' (WP admin default background) for invalid input.
	 *
	 * @since  1.0.0
	 * @param  string $hex     Source hex colour with leading #.
	 * @param  float  $percent Blend strength toward the accent (0–100).
	 * @return string          Tinted #RRGGBB colour.
	 */
	public static function tint_hex( string $hex, float $percent ): string {
		$hex = self::normalize_hex( $hex );

		if ( '' === $hex ) {
			return '#f0f0f1';
		}

		$factor = $percent / 100.0;

		$r = (int) round( 255.0 * ( 1.0 - $factor ) + (float) hexdec( substr( $hex, 1, 2 ) ) * $factor );
		$g = (int) round( 255.0 * ( 1.0 - $factor ) + (float) hexdec( substr( $hex, 3, 2 ) ) * $factor );
		$b = (int) round( 255.0 * ( 1.0 - $factor ) + (float) hexdec( substr( $hex, 5, 2 ) ) * $factor );

		return sprintf(
			'#%02x%02x%02x',
			max( 0, min( 255, $r ) ),
			max( 0, min( 255, $g ) ),
			max( 0, min( 255, $b ) )
		);
	}

	/**
	 * Return R, G, B channel values as a comma-separated string.
	 *
	 * Suitable for use inside CSS rgb() / rgba() functions and for the
	 * --wp-admin-theme-color--rgb custom property.
	 * Returns an empty string for invalid input.
	 *
	 * @since  1.0.0
	 * @param  string $hex Hex colour with leading #.
	 * @return string      e.g. "79, 70, 229", or '' on invalid input.
	 */
	public static function hex_to_rgb( string $hex ): string {
		$hex = self::normalize_hex( $hex );

		if ( '' === $hex ) {
			return '';
		}

		return sprintf(
			'%d, %d, %d',
			hexdec( substr( $hex, 1, 2 ) ),
			hexdec( substr( $hex, 3, 2 ) ),
			hexdec( substr( $hex, 5, 2 ) )
		);
	}

	/**
	 * Return the highest-contrast foreground colour (#000000 or #ffffff) for a
	 * given background hex colour, using the WCAG 2.1 relative-luminance formula.
	 *
	 * @since  1.0.0
	 * @param  string $hex Background colour with leading #.
	 * @return string      '#000000' for light backgrounds, '#ffffff' for dark ones.
	 *                     Falls back to '#ffffff' when $hex is invalid.
	 */
	public static function contrast_color( string $hex ): string {
		$hex = self::normalize_hex( $hex );

		if ( '' === $hex ) {
			return '#ffffff';
		}

		$r_int = (int) hexdec( substr( $hex, 1, 2 ) );
		$g_int = (int) hexdec( substr( $hex, 3, 2 ) );
		$b_int = (int) hexdec( substr( $hex, 5, 2 ) );

		/*
		 * Linearise each sRGB channel per WCAG 2.1 §1.4.3 / IEC 61966-2-1.
		 * Values ≤ 0.04045 use the linear approximation; higher values use
		 * the gamma expansion.
		 */
		$linearise = static function ( int $channel ): float {
			$c = $channel / 255.0;

			return $c <= 0.04045
				? $c / 12.92
				: ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
		};

		$r = $linearise( $r_int );
		$g = $linearise( $g_int );
		$b = $linearise( $b_int );

		// WCAG relative luminance (Y in CIE XYZ).
		$luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

		/*
		 * The threshold 0.179 is derived from the WCAG recommendation:
		 * the geometric mean of luminance values at a contrast ratio of 4.5:1
		 * against both white (1.0) and black (0.0).  Using 0.5 is a common
		 * mistake that causes failures on many mid-range saturated colours.
		 */
		return $luminance > 0.179 ? '#000000' : '#ffffff';
	}
}
