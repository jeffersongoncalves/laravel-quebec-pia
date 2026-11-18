<?php

namespace JeffersonGoncalves\QuebecPia\Tests;

use JeffersonGoncalves\QuebecPia\QuebecPiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            QuebecPiaServiceProvider::class,
        ];
    }
}
