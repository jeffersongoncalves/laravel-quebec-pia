<?php

namespace JeffersonGoncalves\QuebecPia\Tests;

use JeffersonGoncalves\PestPluginDatabaseMigrations\LoadsPackageMigrations;
use JeffersonGoncalves\Quebec\QuebecServiceProvider;
use JeffersonGoncalves\QuebecGovernance\QuebecGovernanceServiceProvider;
use JeffersonGoncalves\QuebecPia\QuebecPiaServiceProvider;
use JeffersonGoncalves\QuebecPia\Tests\Fixtures\Applicant;
use JeffersonGoncalves\QuebecPia\Tests\Fixtures\Customer;
use JeffersonGoncalves\QuebecPia\Tests\Fixtures\Patient;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use LoadsPackageMigrations;

    protected function getPackageProviders($app): array
    {
        return [
            QuebecServiceProvider::class,
            QuebecGovernanceServiceProvider::class,
            QuebecPiaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('quebec.models', [Customer::class, Patient::class, Applicant::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadPackageMigrations(QuebecGovernanceServiceProvider::class);
        $this->loadPackageMigrations(QuebecPiaServiceProvider::class);
    }
}
