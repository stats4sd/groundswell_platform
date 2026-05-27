<?php

declare(strict_types=1);

/**
 * Stub overrides for Pest global functions so PHPStan resolves $this correctly
 * inside test closures. The bound type matches pest()->extend(Tests\TestCase::class)
 * configured in tests/Pest.php.
 */

/**
 * @param-closure-this \Tests\TestCase $closure
 */
function test(?string $description = null, ?Closure $closure = null): mixed {}

/**
 * @param-closure-this \Tests\TestCase $closure
 */
function it(string $description, ?Closure $closure = null): mixed {}

/**
 * @param-closure-this \Tests\TestCase $closure
 */
function beforeEach(?Closure $closure = null): mixed {}

/**
 * @param-closure-this \Tests\TestCase $closure
 */
function afterEach(?Closure $closure = null): mixed {}