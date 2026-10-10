<?php

use App\Http\Controllers\DemoController;
use App\Livewire\Actions\Logout;
use App\Modules\Accounting\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? route('dashboard') : route('login')));

Route::get('dashboard', Dashboard::class)
    ->middleware('auth')
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::post('logout', function (Logout $logout) {
    $logout();

    return redirect('/');
})->middleware('auth')->name('logout');

Route::post('demo', [DemoController::class, 'store'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('demo');

require __DIR__.'/auth.php';
