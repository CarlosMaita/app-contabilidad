<?php

use App\Modules\Accounting\Livewire\ChartOfAccounts;
use App\Modules\Accounting\Livewire\MappingEditor;
use App\Modules\Accounting\Livewire\Mappings;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/accounts', ChartOfAccounts::class)->name('accounts.index');
    Route::get('/mappings', Mappings::class)->name('mappings.index');
    Route::get('/mappings/{operationType}', MappingEditor::class)->name('mappings.edit');
});
