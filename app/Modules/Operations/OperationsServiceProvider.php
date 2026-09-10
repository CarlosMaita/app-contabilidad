<?php

namespace App\Modules\Operations;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Views', 'operations');

        Route::middleware('web')->group(__DIR__.'/routes.php');
    }
}
