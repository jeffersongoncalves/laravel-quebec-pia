<?php

namespace JeffersonGoncalves\QuebecPia;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class QuebecPiaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-quebec-pia')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations();
    }
}
