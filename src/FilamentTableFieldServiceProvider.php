<?php

namespace Momenoor\FilamentTableField;

use Filament\Forms\Components\Component;
use Illuminate\Support\ServiceProvider;
use Momenoor\FilamentTableField\Forms\Components\TableField;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentTableFieldServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-table-field')
            ->hasViews('filament-table-field');
    }

    public function packageBooted(): void
    {
        // Register the TableField component
        Component::macro('tableField', function (string $name): TableField {
            return TableField::make($name);
        });
    }
}
