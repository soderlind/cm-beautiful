<?php
/**
 * Tests for CMB_Color utility class.
 *
 * @package cm-beautiful
 */

declare(strict_types=1);

// --- is_valid_hex() ----------------------------------------------------------

it('accepts valid 6-digit hex colors', function () {
	expect(CMB_Color::is_valid_hex('#2271b1'))->toBeTrue();
	expect(CMB_Color::is_valid_hex('#FFFFFF'))->toBeTrue();
	expect(CMB_Color::is_valid_hex('#000000'))->toBeTrue();
	expect(CMB_Color::is_valid_hex('#4f46e5'))->toBeTrue();
});

it('accepts valid 3-digit hex colors', function () {
	expect(CMB_Color::is_valid_hex('#fff'))->toBeTrue();
	expect(CMB_Color::is_valid_hex('#000'))->toBeTrue();
	expect(CMB_Color::is_valid_hex('#ABC'))->toBeTrue();
});

it('rejects invalid hex colors', function () {
	expect(CMB_Color::is_valid_hex('2271b1'))->toBeFalse();      // Missing #
	expect(CMB_Color::is_valid_hex('#22'))->toBeFalse();         // Too short
	expect(CMB_Color::is_valid_hex('#2271b1ff'))->toBeFalse();   // Too long (8 digits)
	expect(CMB_Color::is_valid_hex('#gggggg'))->toBeFalse();     // Invalid characters
	expect(CMB_Color::is_valid_hex(''))->toBeFalse();            // Empty
	expect(CMB_Color::is_valid_hex('rgb(0,0,0)'))->toBeFalse();  // RGB format
});

// --- normalize_hex() ---------------------------------------------------------

it('expands 3-digit hex to 6-digit', function () {
	expect(CMB_Color::normalize_hex('#abc'))->toBe('#aabbcc');
	expect(CMB_Color::normalize_hex('#FFF'))->toBe('#ffffff');
	expect(CMB_Color::normalize_hex('#123'))->toBe('#112233');
});

it('lowercases 6-digit hex', function () {
	expect(CMB_Color::normalize_hex('#FFFFFF'))->toBe('#ffffff');
	expect(CMB_Color::normalize_hex('#2271B1'))->toBe('#2271b1');
});

it('returns 6-digit hex unchanged except case', function () {
	expect(CMB_Color::normalize_hex('#2271b1'))->toBe('#2271b1');
});

it('returns empty string for invalid hex input', function () {
	expect(CMB_Color::normalize_hex('not-a-color'))->toBe('');
	expect(CMB_Color::normalize_hex(''))->toBe('');
	expect(CMB_Color::normalize_hex('#gg'))->toBe('');
});

// --- darken_hex() ------------------------------------------------------------

it('darkens a color by percentage', function () {
	// White (#ffffff) darkened 10% should be approximately #e6e6e6
	$darkened = CMB_Color::darken_hex('#ffffff', 10);
	expect($darkened)->toBeValidHex();
	expect($darkened)->toBe('#e6e6e6');
});

it('returns black when darkened 100%', function () {
	expect(CMB_Color::darken_hex('#ffffff', 100))->toBe('#000000');
	expect(CMB_Color::darken_hex('#2271b1', 100))->toBe('#000000');
});

it('returns original color when darkened 0%', function () {
	expect(CMB_Color::darken_hex('#2271b1', 0))->toBe('#2271b1');
});

it('returns empty string for invalid darken input', function () {
	expect(CMB_Color::darken_hex('invalid', 10))->toBe('');
});

// --- tint_hex() --------------------------------------------------------------

it('tints a color toward white', function () {
	// Black (#000000) tinted 10% should blend toward white
	$tinted = CMB_Color::tint_hex('#000000', 10);
	expect($tinted)->toBeValidHex();
	// 10% black + 90% white ≈ #e6e6e6
	expect($tinted)->toBe('#e6e6e6');
});

it('returns near-white for low tint percentage', function () {
	// 5% accent means 95% white
	$tinted = CMB_Color::tint_hex('#000000', 5);
	expect($tinted)->toBe('#f2f2f2');
});

it('returns original color at 100% tint', function () {
	expect(CMB_Color::tint_hex('#2271b1', 100))->toBe('#2271b1');
});

it('returns fallback for invalid tint input', function () {
	expect(CMB_Color::tint_hex('invalid', 10))->toBe('#f0f0f1');
});

// --- hex_to_rgb() ------------------------------------------------------------

it('converts hex to RGB string', function () {
	expect(CMB_Color::hex_to_rgb('#ffffff'))->toBe('255, 255, 255');
	expect(CMB_Color::hex_to_rgb('#000000'))->toBe('0, 0, 0');
	expect(CMB_Color::hex_to_rgb('#4f46e5'))->toBe('79, 70, 229');
});

it('handles 3-digit hex for RGB conversion', function () {
	expect(CMB_Color::hex_to_rgb('#fff'))->toBe('255, 255, 255');
	expect(CMB_Color::hex_to_rgb('#000'))->toBe('0, 0, 0');
});

it('returns empty string for invalid RGB input', function () {
	expect(CMB_Color::hex_to_rgb('invalid'))->toBe('');
});

// --- contrast_color() --------------------------------------------------------

it('returns black for light backgrounds', function () {
	expect(CMB_Color::contrast_color('#ffffff'))->toBe('#000000');
	expect(CMB_Color::contrast_color('#f0f0f1'))->toBe('#000000');
	expect(CMB_Color::contrast_color('#ffff00'))->toBe('#000000'); // Yellow
});

it('returns white for dark backgrounds', function () {
	expect(CMB_Color::contrast_color('#000000'))->toBe('#ffffff');
	expect(CMB_Color::contrast_color('#2271b1'))->toBe('#ffffff'); // WP blue
	expect(CMB_Color::contrast_color('#4f46e5'))->toBe('#ffffff'); // Indigo
	expect(CMB_Color::contrast_color('#334155'))->toBe('#ffffff'); // Slate
});

it('returns white for invalid contrast input', function () {
	expect(CMB_Color::contrast_color('invalid'))->toBe('#ffffff');
	expect(CMB_Color::contrast_color(''))->toBe('#ffffff');
});

// --- WCAG contrast for darkened tones ----------------------------------------

it('computes correct contrast for darkened accent tones', function () {
	// A light accent like pale yellow needs black text.
	$lightAccent = '#fff9c4'; // Pale yellow
	expect(CMB_Color::contrast_color($lightAccent))->toBe('#000000');

	// Darkened versions of a light color should still get appropriate contrast.
	$d10 = CMB_Color::darken_hex($lightAccent, 10);
	$d20 = CMB_Color::darken_hex($lightAccent, 20);
	$d30 = CMB_Color::darken_hex($lightAccent, 30);

	// All darkened tones of pale yellow should still use black text (WCAG).
	expect(CMB_Color::contrast_color($d10))->toBe('#000000');
	expect(CMB_Color::contrast_color($d20))->toBe('#000000');
	expect(CMB_Color::contrast_color($d30))->toBe('#000000');
});

it('computes correct contrast for very dark colors', function () {
	// Very dark colors need white text.
	$darkAccent = '#1a1a2e'; // Very dark blue
	expect(CMB_Color::contrast_color($darkAccent))->toBe('#ffffff');

	$d10 = CMB_Color::darken_hex($darkAccent, 10);
	$d20 = CMB_Color::darken_hex($darkAccent, 20);
	$d30 = CMB_Color::darken_hex($darkAccent, 30);

	// Darkened versions remain dark and need white text.
	expect(CMB_Color::contrast_color($d10))->toBe('#ffffff');
	expect(CMB_Color::contrast_color($d20))->toBe('#ffffff');
	expect(CMB_Color::contrast_color($d30))->toBe('#ffffff');
});
