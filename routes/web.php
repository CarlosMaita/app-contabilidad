<?php

use App\Http\Controllers\DemoController;
use App\Livewire\Actions\Logout;
use App\Livewire\Books\ManageBooks;
use App\Livewire\Books\TransferBetweenBooks;
use App\Modules\Accounting\Livewire\Dashboard;
use App\Modules\Shared\Books\Book;
use App\Modules\Shared\Books\CurrentBook;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? route('dashboard') : route('login')));

Route::get('dashboard', Dashboard::class)
    ->middleware('auth')
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Contabilidades: gestión y cambio de la contabilidad actual.
Route::middleware('auth')->group(function (): void {
    Route::get('books', ManageBooks::class)->name('books.index');
    Route::get('transfers', TransferBetweenBooks::class)->name('transfers.create');

    Route::post('books/{book}/switch', function (Book $book) {
        CurrentBook::switchTo(auth()->user(), $book);

        return redirect()->route('dashboard')->with('status', "Ahora trabajás en «{$book->label()}».");
    })->name('books.switch');
});

Route::post('logout', function (Logout $logout) {
    $logout();

    return redirect('/');
})->middleware('auth')->name('logout');

Route::post('demo', [DemoController::class, 'store'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('demo');

require __DIR__.'/auth.php';
