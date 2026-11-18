<?php

namespace JeffersonGoncalves\QuebecPia;

use JeffersonGoncalves\QuebecPia\Commands\PiaCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class QuebecPiaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('quebec-pia')
            ->hasConfigFile()
            ->hasMigrations(['create_quebec_impact_assessments_table'])
            ->hasCommand(PiaCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PiaManager::class);
    }
}
