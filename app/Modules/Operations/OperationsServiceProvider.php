<?php

namespace App\Modules\Operations;

use App\Modules\Operations\Livewire\EventLog;
use App\Modules\Operations\Livewire\ExecuteOperationForm;
use App\Modules\Operations\Livewire\OperationTypes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class OperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Views', 'operations');

        Livewire::component('operations.operation-types', OperationTypes::class);
        Livewire::component('operations.execute-operation-form', ExecuteOperationForm::class);
        Livewire::component('operations.event-log', EventLog::class);

        Route::middleware('web')->group(__DIR__.'/routes.php');
    }
}
