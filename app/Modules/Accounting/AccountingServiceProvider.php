<?php

namespace App\Modules\Accounting;

use App\Modules\Accounting\Listeners\SeedDefaultChartOfAccountsOnRegistration;
use App\Modules\Accounting\Livewire\ChartOfAccounts;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AccountingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Views', 'accounting');

        Event::listen(Registered::class, SeedDefaultChartOfAccountsOnRegistration::class);

        Livewire::component('accounting.chart-of-accounts', ChartOfAccounts::class);

        Route::middleware('web')->group(__DIR__.'/routes.php');
    }
}
