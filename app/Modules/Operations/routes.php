<?php

use App\Modules\Operations\Livewire\EventLog;
use App\Modules\Operations\Livewire\ExecuteOperationForm;
use App\Modules\Operations\Livewire\OperationTypes;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/operations', OperationTypes::class)->name('operations.index');
    Route::get('/operations/{operationType}/execute', ExecuteOperationForm::class)->name('operations.execute');
    Route::get('/events', EventLog::class)->name('events.index');
});
