<?php

use App\Modules\Accounting\Http\ExportController;
use App\Modules\Accounting\Livewire\ChartOfAccounts;
use App\Modules\Accounting\Livewire\GeneralLedger;
use App\Modules\Accounting\Livewire\JournalBook;
use App\Modules\Accounting\Livewire\MappingEditor;
use App\Modules\Accounting\Livewire\Mappings;
use App\Modules\Accounting\Livewire\Reports;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/accounts', ChartOfAccounts::class)->name('accounts.index');
    Route::get('/mappings', Mappings::class)->name('mappings.index');
    Route::get('/mappings/{operationType}', MappingEditor::class)->name('mappings.edit');
    Route::get('/journal', JournalBook::class)->name('journal.index');
    Route::get('/ledger', GeneralLedger::class)->name('ledger.index');
    Route::get('/reports', Reports::class)->name('reports.index');

    Route::get('/exports/balance', [ExportController::class, 'balance'])->name('exports.balance');
    Route::get('/exports/pnl', [ExportController::class, 'pnl'])->name('exports.pnl');
    Route::get('/exports/journal', [ExportController::class, 'journal'])->name('exports.journal');
    Route::get('/exports/ledger', [ExportController::class, 'ledger'])->name('exports.ledger');
});
