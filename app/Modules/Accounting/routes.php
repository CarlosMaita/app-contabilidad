<?php

use App\Modules\Accounting\Livewire\ChartOfAccounts;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/accounts', ChartOfAccounts::class)->name('accounts.index');
});
