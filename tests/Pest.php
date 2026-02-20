<?php
/**
 * Pest.php configuration file.
 *
 * @package cm-beautiful
 */

declare(strict_types=1);

use Brain\Monkey;

/*
|--------------------------------------------------------------------------
| Uses: Brain Monkey for all tests
|--------------------------------------------------------------------------
*/
uses()
	->beforeEach(function () {
		Monkey\setUp();
	})
	->afterEach(function () {
		Monkey\tearDown();
	})
	->in('unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/
expect()->extend('toBeValidHex', function () {
	return $this->toMatch('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/');
});
