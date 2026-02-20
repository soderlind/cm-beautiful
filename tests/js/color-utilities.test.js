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
