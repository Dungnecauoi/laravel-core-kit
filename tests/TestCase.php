<?php

namespace LaravelCore\Tests;

use LaravelCore\CoreServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class];
    }
}
