<?php
/**
 * Tests for CMB_Profile user profile handler.
 *
 * @package cm-beautiful
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

beforeEach(function () {
	// Load CMB_Profile class (depends on CMB_Color which is loaded in bootstrap).
	require_once CMB_PLUGIN_DIR . 'includes/class-cmb-profile.php';

	// Stub __() to return the string as-is.
	Functions\stubs([
		'__' => static fn(string $text, string $domain = 'default'): string => $text,
	]);
});

// --- PRESETS constant --------------------------------------------------------

it('has expected preset keys', function () {
	$keys = array_keys(CMB_Profile::PRESETS);

	expect($keys)->toContain('follow_wp');
	expect($keys)->toContain('neutral_blue');
	expect($keys)->toContain('indigo');
	expect($keys)->toContain('teal');
	expect($keys)->toContain('green');
	expect($keys)->toContain('amber');
	expect($keys)->toContain('red');
	expect($keys)->toContain('slate');
});

it('has null hex for follow_wp preset', function () {
	expect(CMB_Profile::PRESETS['follow_wp']['hex'])->toBeNull();
});

it('has valid hex values for all color presets', function () {
	foreach (CMB_Profile::PRESETS as $key => $data) {
		if ($key === 'follow_wp') {
			continue;
		}
		expect($data['hex'])->toBeValidHex();
	}
});

it('has labels for all presets', function () {
	foreach (CMB_Profile::PRESETS as $key => $data) {
		expect($data['label'])->toBeString();
		expect($data['label'])->not->toBeEmpty();
	}
});

// --- get_preset_label() ------------------------------------------------------

it('returns translated label for valid preset', function () {
	expect(CMB_Profile::get_preset_label('follow_wp'))->toBe('Follow WordPress');
	expect(CMB_Profile::get_preset_label('indigo'))->toBe('Indigo');
	expect(CMB_Profile::get_preset_label('teal'))->toBe('Teal');
});

it('returns key for unknown preset', function () {
	expect(CMB_Profile::get_preset_label('unknown_preset'))->toBe('unknown_preset');
});

// --- META_* constants --------------------------------------------------------

it('defines META_PRESET constant', function () {
	expect(CMB_Profile::META_PRESET)->toBe('cmb_ui_preset');
});

it('defines META_CUSTOM_ACCENT constant', function () {
	expect(CMB_Profile::META_CUSTOM_ACCENT)->toBe('cmb_ui_custom_accent');
});

it('defines META_NIGHT_MODE constant', function () {
	expect(CMB_Profile::META_NIGHT_MODE)->toBe('cmb_ui_night_mode');
});

// --- register_hooks() --------------------------------------------------------

it('registers WordPress hooks', function () {
	$hooks = [];

	Functions\when('add_action')->alias(function ($hook, $callback) use (&$hooks) {
		$hooks[] = $hook;
		return true;
	});

	$profile = new CMB_Profile();
	$profile->register_hooks();

	expect($hooks)->toContain('personal_options');
	expect($hooks)->toContain('personal_options_update');
	expect($hooks)->toContain('edit_user_profile_update');
});

// --- save_profile_fields() ---------------------------------------------------

it('returns early without valid nonce', function () {
	$_POST = [];

	$updateCalled = false;
	Functions\when('update_user_meta')->alias(function () use (&$updateCalled) {
		$updateCalled = true;
	});

	$profile = new CMB_Profile();
	$profile->save_profile_fields(1);

	expect($updateCalled)->toBeFalse();
});

it('saves valid preset to user meta', function () {
	$_POST = [
		'cmb_profile_nonce' => 'valid_nonce',
		CMB_Profile::META_PRESET => 'indigo',
		CMB_Profile::META_CUSTOM_ACCENT => '',
	];

	Functions\when('wp_verify_nonce')->justReturn(true);
	Functions\when('sanitize_text_field')->returnArg(1);
	Functions\when('sanitize_key')->returnArg(1);
	Functions\when('wp_unslash')->returnArg(1);
	Functions\when('current_user_can')->justReturn(true);

	$savedPreset = null;
	Functions\when('update_user_meta')->alias(function ($user_id, $key, $value) use (&$savedPreset) {
		if ($key === CMB_Profile::META_PRESET) {
			$savedPreset = $value;
		}
	});
	Functions\when('delete_user_meta')->justReturn(true);

	$profile = new CMB_Profile();
	$profile->save_profile_fields(1);

	expect($savedPreset)->toBe('indigo');
});

it('falls back to follow_wp for invalid preset', function () {
	$_POST = [
		'cmb_profile_nonce' => 'valid_nonce',
		CMB_Profile::META_PRESET => 'invalid_preset',
	];

	Functions\when('wp_verify_nonce')->justReturn(true);
	Functions\when('sanitize_text_field')->returnArg(1);
	Functions\when('sanitize_key')->returnArg(1);
	Functions\when('wp_unslash')->returnArg(1);
	Functions\when('current_user_can')->justReturn(true);

	$savedPreset = null;
	Functions\when('update_user_meta')->alias(function ($user_id, $key, $value) use (&$savedPreset) {
		if ($key === CMB_Profile::META_PRESET) {
			$savedPreset = $value;
		}
	});
	Functions\when('delete_user_meta')->justReturn(true);

	$profile = new CMB_Profile();
	$profile->save_profile_fields(1);

	expect($savedPreset)->toBe('follow_wp');
});

it('returns early without edit_user capability', function () {
	$_POST = [
		'cmb_profile_nonce' => 'valid_nonce',
		CMB_Profile::META_PRESET => 'indigo',
	];

	Functions\when('wp_verify_nonce')->justReturn(true);
	Functions\when('sanitize_text_field')->returnArg(1);
	Functions\when('wp_unslash')->returnArg(1);
	Functions\when('current_user_can')->justReturn(false);

	$updateCalled = false;
	Functions\when('update_user_meta')->alias(function () use (&$updateCalled) {
		$updateCalled = true;
	});

	$profile = new CMB_Profile();
	$profile->save_profile_fields(1);

	expect($updateCalled)->toBeFalse();
});
