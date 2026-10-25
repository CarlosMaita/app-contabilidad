<?php

use App\Demo\DemoSandbox;
use App\Models\User;
use App\Modules\Accounting\Livewire\Dashboard;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BookTransfer;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\ReportService;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationExecution;
use Database\Seeders\DemoSeeder;
use Livewire\Livewire;

test('el login ofrece entrar como demo', function () {
    $this->get('/login')->assertOk()->assertSee('Ver como demo')->assertSee(route('demo'), false);
});

test('ver como demo crea una cuenta descartable con datos de ejemplo y entra', function () {
    $this->post('/demo')->assertRedirect(route('dashboard'));

    $user = auth()->user();
    expect($user)->not->toBeNull()
        ->and($user->is_demo)->toBeTrue();

    $this->get('/dashboard')->assertOk()->assertSee('cuenta de prueba');

    $entries = JournalEntry::withoutGlobalScopes()->where('book_id', bookOf($user)->id);
    $executions = OperationExecution::withoutGlobalScopes()->where('book_id', bookOf($user)->id);

    expect($entries->count())->toBeGreaterThan(20)
        ->and((clone $executions)->where('status', ExecutionStatus::Unmapped)->count())->toBe(1)
        ->and((clone $executions)->where('status', ExecutionStatus::Voided)->count())->toBe(1)
        ->and((clone $executions)->where('status', ExecutionStatus::Failed)->count())->toBe(0)
        ->and(Account::withoutGlobalScopes()->where('book_id', bookOf($user)->id)->where('is_auxiliary', true)->count())->toBe(3);

    $reports = app(ReportService::class);
    $balance = $reports->balanceSheet(bookOf($user)->id, now()->toDateString());
    $cashFlow = $reports->cashFlow(bookOf($user)->id, now()->subMonths(3)->toDateString(), now()->toDateString());
    $month = $reports->profitAndLoss(bookOf($user)->id, now()->startOfMonth()->toDateString(), now()->toDateString());

    expect($balance['check']['balanced'])->toBeTrue()
        ->and($cashFlow['check']['balanced'])->toBeTrue()
        ->and((float) $month['total_income'])->toBeGreaterThan(0);

    Livewire::test(Dashboard::class)->assertOk();
});

test('cada visitante demo tiene su propia cuenta', function () {
    $this->post('/demo');
    $first = auth()->id();
    auth()->logout();

    $this->post('/demo');

    expect(auth()->id())->not->toBe($first)
        ->and(User::where('is_demo', true)->count())->toBe(2);
});

test('al salir se borra la cuenta demo con todos sus datos', function () {
    $this->post('/demo');
    $id = auth()->id();
    $bookId = auth()->user()->personalBook()->id;

    $this->post('/logout')->assertRedirect('/');

    expect(User::find($id))->toBeNull()
        ->and(JournalEntry::withoutGlobalScopes()->where('book_id', $bookId)->count())->toBe(0)
        ->and(Account::withoutGlobalScopes()->where('book_id', $bookId)->count())->toBe(0);
});

test('salir con una cuenta normal no la borra', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout');

    expect(User::find($user->id))->not->toBeNull();
});

test('las cuentas demo vencidas se purgan y las recientes no', function () {
    $sandbox = app(DemoSandbox::class);
    $old = $sandbox->create();
    $old->forceFill(['created_at' => now()->subHours(DemoSandbox::LIFETIME_HOURS + 1)])->save();
    $recent = $sandbox->create();
    $regular = User::factory()->create(['created_at' => now()->subYear()]);

    $this->artisan('demo:purge')->assertSuccessful();

    expect(User::find($old->id))->toBeNull()
        ->and(User::find($recent->id))->not->toBeNull()
        ->and(User::find($regular->id))->not->toBeNull();
});

test('la cuenta demo no puede cambiar correo ni contraseña', function () {
    $this->post('/demo');

    $this->get('/profile')->assertOk()
        ->assertSee('Esta es una cuenta demo')
        ->assertDontSee('update-password', false);
});

test('el seeder crea el usuario demo fijo', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    $user = User::where('email', DemoSeeder::EMAIL)->sole();

    expect($user->is_demo)->toBeFalse()
        ->and(JournalEntry::withoutGlobalScopes()->where('book_id', bookOf($user)->id)->count())->toBeGreaterThan(20);
});

test('la demo trae una contabilidad familiar con aportes y gastos comunes', function () {
    $this->post('/demo');
    $user = auth()->user();

    $family = $user->books()->where('kind', 'shared')->sole();
    $reports = app(ReportService::class);
    $balance = $reports->balanceSheet($family->id, now()->toDateString());

    expect($family->name)->toBe('Familia (demo)')
        ->and(BookTransfer::where('to_book_id', $family->id)->count())->toBe(2)
        ->and(JournalEntry::withoutGlobalScopes()->where('book_id', $family->id)->count())->toBe(9)
        ->and($balance['check']['balanced'])->toBeTrue()
        ->and($balance['sections']['asset']['total'])->toBe('246800.00'); // 750.000 aportados − 503.200 de gastos

    $this->post(route('books.switch', $family));
    $this->get(route('dashboard'))->assertOk()->assertSee('Familia (demo)')->assertSee('Aportar desde mi contabilidad');
});
