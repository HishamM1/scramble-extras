<?php

namespace PawelJadanowski\ScrambleExtras\Tests;

use Dedoc\Scramble\ScrambleServiceProvider;
use PawelJadanowski\ScrambleExtras\ScrambleExtrasServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ScrambleServiceProvider::class,
            ScrambleExtrasServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Keep schema generation deterministic across tests: never read a
        // schema cache file written by a previous run.
        $app['config']->set('scramble-extras.cache.enabled', false);
    }
}
