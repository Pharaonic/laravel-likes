<?php

use Illuminate\Contracts\Console\Kernel;
use Pharaonic\Laravel\Likes\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * The package migration, as Laravel records it.
 */
const LIKES_MIGRATION = '2026_11_04_000001_create_pharaonic_likes_table';

/**
 * Run an Artisan command and expect it to succeed.
 *
 * @param  array<string, mixed>  $parameters
 */
function runArtisan(string $command, array $parameters = []): void
{
    expect(app(Kernel::class)->call($command, $parameters))->toBe(0);
}
