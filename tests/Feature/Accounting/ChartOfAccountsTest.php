<?php

use App\Models\User;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Livewire\ChartOfAccounts;
use App\Modules\Accounting\Models\Account;
use Illuminate\Auth\Events\Registered;
use Livewire\Livewire;

test('la página del plan de cuentas requiere autenticación', function () {
    $this->get('/accounts')->assertRedirect('/login');
});

test('la página del plan de cuentas se renderiza para un usuario autenticado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/accounts')
        ->assertOk()
        ->assertSeeLivewire(ChartOfAccounts::class);
});

test('cada usuario solo ve sus propias cuentas', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Account::factory()->for($userA)->create(['code' => '1', 'name' => 'Caja A']);
    Account::factory()->for($userB)->create(['code' => '1', 'name' => 'Caja B']);

    $this->actingAs($userA);

    expect(Account::all())->toHaveCount(1)
        ->and(Account::first()->name)->toBe('Caja A');
});

test('el código de cuenta es único por usuario pero puede repetirse entre usuarios', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Account::factory()->for($userA)->create(['code' => '1.1']);
    Account::factory()->for($userB)->create(['code' => '1.1']);

    expect(Account::withoutGlobalScopes()->where('code', '1.1')->count())->toBe(2);

    $this->actingAs($userA);

    Livewire::test(ChartOfAccounts::class)
        ->call('create')
        ->set('code', '1.1')
        ->set('name', 'Duplicada')
        ->set('type', AccountType::Asset->value)
        ->call('save')
        ->assertHasErrors(['code']);
});

test('al registrarse se precarga el plan de cuentas base', function () {
    $user = User::factory()->create();

    event(new Registered($user));

    $accounts = Account::withoutGlobalScopes()->where('user_id', $user->id)->get();

    expect($accounts)->toHaveCount(41)
        ->and($accounts->firstWhere('code', '1.1.01')->name)->toBe('Caja')
        ->and($accounts->firstWhere('code', '1')->is_postable)->toBeFalse()
        ->and($accounts->firstWhere('code', '4.1')->is_postable)->toBeFalse()
        ->and($accounts->firstWhere('code', '4.1.01')->is_postable)->toBeTrue()
        // Cada rama de resultado lleva su sección del P&L.
        ->and($accounts->firstWhere('code', '5.1')->pnl_section->value)->toBe('cogs')
        ->and($accounts->firstWhere('code', '6.2.01')->pnl_section->value)->toBe('depreciation')
        ->and($accounts->firstWhere('code', '6.3.01')->pnl_section->value)->toBe('financial_expense')
        ->and($accounts->firstWhere('code', '6.4.01')->pnl_section->value)->toBe('tax')
        ->and($accounts->firstWhere('code', '1.1.01')->pnl_section)->toBeNull();

    // El evento es idempotente: no duplica el plan.
    event(new Registered($user));
    expect(Account::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(41);
});

test('se puede crear una cuenta desde el componente livewire', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ChartOfAccounts::class)
        ->call('create')
        ->set('code', '1')
        ->set('name', 'Activo')
        ->set('type', AccountType::Asset->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(Account::where('code', '1')->first())
        ->not->toBeNull()
        ->name->toBe('Activo')
        ->user_id->toBe($user->id);
});

test('una sub-cuenta hereda el tipo del padre y el padre deja de ser imputable', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $parent = Account::factory()->for($user)->create([
        'code' => '4',
        'type' => AccountType::Income,
        'is_postable' => true,
    ]);

    Livewire::test(ChartOfAccounts::class)
        ->call('create', $parent->id)
        ->set('code', '4.1')
        ->set('name', 'Ventas')
        ->set('type', AccountType::Expense->value) // Intento inconsistente: debe imponerse el del padre.
        ->call('save')
        ->assertHasNoErrors();

    $child = Account::where('code', '4.1')->first();

    expect($child->type)->toBe(AccountType::Income)
        ->and($child->parent_id)->toBe($parent->id)
        ->and($parent->fresh()->is_postable)->toBeFalse();
});

test('no se puede eliminar una cuenta con sub-cuentas', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $parent = Account::factory()->for($user)->create(['code' => '1']);
    Account::factory()->for($user)->create(['code' => '1.1', 'parent_id' => $parent->id]);

    Livewire::test(ChartOfAccounts::class)->call('delete', $parent->id);

    expect(Account::find($parent->id))->not->toBeNull();
});

test('editar una cuenta no permite ciclos de padre', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $parent = Account::factory()->for($user)->create(['code' => '1', 'type' => AccountType::Asset]);
    $child = Account::factory()->for($user)->create(['code' => '1.1', 'parent_id' => $parent->id, 'type' => AccountType::Asset]);

    Livewire::test(ChartOfAccounts::class)
        ->call('edit', $parent->id)
        ->set('parent_id', (string) $child->id)
        ->call('save')
        ->assertHasErrors(['parent_id']);
});
