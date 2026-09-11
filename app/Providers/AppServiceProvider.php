<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Accounting\Services\PurgeAccountingData;
use App\Modules\Operations\Services\PurgeOperationsData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Al eliminar la cuenta de usuario se purgan sus datos de negocio
        // en orden seguro de FKs: primero lo contable (los asientos
        // referencian ejecuciones y mapeos), después lo operativo.
        User::deleting(function (User $user): void {
            DB::transaction(function () use ($user): void {
                PurgeAccountingData::forUser($user);
                PurgeOperationsData::forUser($user);
            });
        });
    }
}
