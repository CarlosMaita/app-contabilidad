<?php

namespace App\Modules\Accounting;

use App\Modules\Accounting\Console\SyncChartOfAccounts;
use App\Modules\Accounting\Listeners\GenerateJournalEntryFromOperation;
use App\Modules\Accounting\Listeners\SeedDefaultChartOfAccountsOnRegistration;
use App\Modules\Accounting\Livewire\ChartOfAccounts;
use App\Modules\Accounting\Livewire\Dashboard;
use App\Modules\Accounting\Livewire\GeneralLedger;
use App\Modules\Accounting\Livewire\JournalBook;
use App\Modules\Accounting\Livewire\ManualEntryForm;
use App\Modules\Accounting\Livewire\MappingEditor;
use App\Modules\Accounting\Livewire\Mappings;
use App\Modules\Accounting\Livewire\Reports;
use App\Modules\Accounting\Services\EloquentChartOfAccountsProvider;
use App\Modules\Accounting\Services\OperationVoider;
use App\Modules\Operations\Events\OperationExecuted;
use App\Modules\Shared\Contracts\ChartOfAccountsProvider;
use App\Modules\Shared\Contracts\VoidsOperationExecutions;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ChartOfAccountsProvider::class, EloquentChartOfAccountsProvider::class);
        $this->app->bind(VoidsOperationExecutions::class, OperationVoider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Views', 'accounting');

        Event::listen(Registered::class, SeedDefaultChartOfAccountsOnRegistration::class);
        Event::listen(OperationExecuted::class, GenerateJournalEntryFromOperation::class);

        Livewire::component('accounting.chart-of-accounts', ChartOfAccounts::class);
        Livewire::component('accounting.mappings', Mappings::class);
        Livewire::component('accounting.mapping-editor', MappingEditor::class);
        Livewire::component('accounting.journal-book', JournalBook::class);
        Livewire::component('accounting.general-ledger', GeneralLedger::class);
        Livewire::component('accounting.reports', Reports::class);
        Livewire::component('accounting.manual-entry-form', ManualEntryForm::class);
        Livewire::component('accounting.dashboard', Dashboard::class);

        Route::middleware('web')->group(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([SyncChartOfAccounts::class]);
        }
    }
}
