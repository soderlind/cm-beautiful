/**
 * Color utility tests
 *
 * These test hex color validation and manipulation utilities
 * that could be extracted from the profile-color-picker.js
 */

import { describe, it, expect } from 'vitest';

/**
 * Validates a hex color string
 * @param {string} hex
 * @returns {boolean}
 */
function isValidHex(hex) {
	return /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(hex);
}

/**
 * Normalizes a hex color to 6-digit lowercase
 * @param {string} hex
 * @returns {string}
 */
function normalizeHex(hex) {
	if (!isValidHex(hex)) return '';
	hex = hex.toLowerCase();
	if (hex.length === 4) {
		return '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
	}
	return hex;
}

/**
 * WCAG 2.1 relative-luminance contrast color.
 * Returns black for light backgrounds, white for dark backgrounds.
 * @param {string} hex
 * @returns {string} '#000000' or '#ffffff'
 */
function contrastColor(hex) {
	hex = normalizeHex(hex);
	if (!hex) return '#ffffff';

	const linearise = (c) => {
		c /= 255;
		return c <= 0.04045
			? c / 12.92
			: Math.pow((c + 0.055) / 1.055, 2.4);
	};

	const r = linearise(parseInt(hex.slice(1, 3), 16));
	const g = linearise(parseInt(hex.slice(3, 5), 16));
	const b = linearise(parseInt(hex.slice(5, 7), 16));

	// WCAG relative luminance
	const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;

	// Threshold 0.179 for WCAG AA contrast ratio
	return luminance > 0.179 ? '#000000' : '#ffffff';
}

describe('isValidHex', () => {
	it('accepts valid 6-digit hex colors', () => {
		expect(isValidHex('#2271b1')).toBe(true);
		expect(isValidHex('#FFFFFF')).toBe(true);
		expect(isValidHex('#000000')).toBe(true);
	});

	it('accepts valid 3-digit hex colors', () => {
		expect(isValidHex('#fff')).toBe(true);
		expect(isValidHex('#000')).toBe(true);
		expect(isValidHex('#ABC')).toBe(true);
	});

	it('rejects invalid hex colors', () => {
		expect(isValidHex('2271b1')).toBe(false);       // Missing #
		expect(isValidHex('#22')).toBe(false);          // Too short
		expect(isValidHex('#2271b1ff')).toBe(false);    // Too long
		expect(isValidHex('#gggggg')).toBe(false);      // Invalid chars
		expect(isValidHex('')).toBe(false);             // Empty
	});
});

describe('normalizeHex', () => {
	it('expands 3-digit hex to 6-digit', () => {
		expect(normalizeHex('#abc')).toBe('#aabbcc');
		expect(normalizeHex('#FFF')).toBe('#ffffff');
		expect(normalizeHex('#123')).toBe('#112233');
	});

	it('lowercases 6-digit hex', () => {
		expect(normalizeHex('#FFFFFF')).toBe('#ffffff');
		expect(normalizeHex('#2271B1')).toBe('#2271b1');
	});

	it('returns empty string for invalid input', () => {
		expect(normalizeHex('not-a-color')).toBe('');
		expect(normalizeHex('')).toBe('');
	});
});

describe('contrastColor (WCAG)', () => {
	it('returns black for light backgrounds', () => {
		expect(contrastColor('#ffffff')).toBe('#000000');
		expect(contrastColor('#f0f0f1')).toBe('#000000');
		expect(contrastColor('#ffff00')).toBe('#000000'); // Yellow
		expect(contrastColor('#fff9c4')).toBe('#000000'); // Pale yellow
	});

	it('returns white for dark backgrounds', () => {
		expect(contrastColor('#000000')).toBe('#ffffff');
		expect(contrastColor('#2271b1')).toBe('#ffffff'); // WP blue
		expect(contrastColor('#4f46e5')).toBe('#ffffff'); // Indigo
		expect(contrastColor('#334155')).toBe('#ffffff'); // Slate
	});

	it('returns white for invalid input', () => {
		expect(contrastColor('invalid')).toBe('#ffffff');
		expect(contrastColor('')).toBe('#ffffff');
	});

	it('handles 3-digit hex', () => {
		expect(contrastColor('#fff')).toBe('#000000');
		expect(contrastColor('#000')).toBe('#ffffff');
	});
});
