<?php

use App\Models\User;
use App\Modules\Operations\Livewire\OperationTypes;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Operations\Models\OperationType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('la página de operaciones requiere autenticación', function () {
    $this->get('/operations')->assertRedirect('/login');
});

test('la página de operaciones se renderiza para un usuario autenticado', function () {
    $this->actingAs(User::factory()->create())
        ->get('/operations')
        ->assertOk()
        ->assertSeeLivewire(OperationTypes::class);
});

test('se puede crear un tipo de operación con variables', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(OperationTypes::class)
        ->call('create')
        ->set('name', 'Pago a proveedor')
        ->set('code', '')
        ->set('variables', [
            ['id' => null, 'name' => 'monto', 'label' => 'Monto', 'type' => 'decimal', 'is_required' => true, 'default_value' => ''],
            ['id' => null, 'name' => 'proveedor', 'label' => 'Proveedor', 'type' => 'string', 'is_required' => false, 'default_value' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $type = OperationType::where('code', 'pago_a_proveedor')->first();

    expect($type)->not->toBeNull()
        ->and($type->user_id)->toBe($user->id)
        ->and($type->variables)->toHaveCount(2)
        ->and($type->variables->pluck('name')->all())->toBe(['monto', 'proveedor']);
});

test('los nombres de variables deben ser snake_case y únicos', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(OperationTypes::class)
        ->call('create')
        ->set('name', 'Cobro')
        ->set('variables', [
            ['id' => null, 'name' => 'Monto Total', 'label' => 'Monto', 'type' => 'decimal', 'is_required' => true, 'default_value' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['variables.0.name']);

    Livewire::test(OperationTypes::class)
        ->call('create')
        ->set('name', 'Cobro')
        ->set('variables', [
            ['id' => null, 'name' => 'monto', 'label' => 'Monto', 'type' => 'decimal', 'is_required' => true, 'default_value' => ''],
            ['id' => null, 'name' => 'monto', 'label' => 'Otro', 'type' => 'decimal', 'is_required' => true, 'default_value' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['variables.0.name', 'variables.1.name']);
});

test('el código es único por usuario', function () {
    $user = User::factory()->create();
    OperationType::factory()->for($user)->create(['code' => 'pago']);
    $this->actingAs($user);

    Livewire::test(OperationTypes::class)
        ->call('create')
        ->set('name', 'Pago')
        ->set('code', 'pago')
        ->call('save')
        ->assertHasErrors(['code']);
});

test('no se puede eliminar un tipo con ejecuciones', function () {
    $user = User::factory()->create();
    $type = OperationType::factory()->for($user)->create();
    OperationExecution::factory()->for($user)->create(['operation_type_id' => $type->id]);
    $this->actingAs($user);

    Livewire::test(OperationTypes::class)->call('delete', $type->id);

    expect(OperationType::withoutGlobalScopes()->find($type->id))->not->toBeNull();
});

test('un usuario no puede editar tipos de operación de otro usuario', function () {
    $other = User::factory()->create();
    $foreignType = OperationType::factory()->for($other)->create();

    $this->actingAs(User::factory()->create());

    expect(fn () => Livewire::test(OperationTypes::class)->call('edit', $foreignType->id))
        ->toThrow(ModelNotFoundException::class);
});
