<?php

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Events\OperationExecuted;
use App\Modules\Operations\Livewire\EventLog;
use App\Modules\Operations\Livewire\ExecuteOperationForm;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Operations\Models\OperationVariable;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

function makePaymentOperation(User $user): OperationType
{
    $type = OperationType::factory()->for($user)->create(['name' => 'Pago', 'code' => 'pago']);
    OperationVariable::factory()->for($type, 'operationType')->create(['name' => 'monto', 'label' => 'Monto', 'type' => 'decimal', 'sort_order' => 0]);
    OperationVariable::factory()->for($type, 'operationType')->create(['name' => 'detalle', 'label' => 'Detalle', 'type' => 'string', 'is_required' => false, 'sort_order' => 1]);

    return $type;
}

test('el formulario de ejecución se renderiza con las variables del tipo', function () {
    $user = User::factory()->create();
    $type = makePaymentOperation($user);

    $this->actingAs($user)
        ->get("/operations/{$type->id}/execute")
        ->assertOk()
        ->assertSee('Monto')
        ->assertSee('Detalle');
});

test('no se puede ejecutar la operación de otro usuario', function () {
    $type = makePaymentOperation(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get("/operations/{$type->id}/execute")
        ->assertNotFound();
});

test('la validación dinámica exige las variables requeridas y su tipo', function () {
    $user = User::factory()->create();
    $type = makePaymentOperation($user);
    $this->actingAs($user);

    Livewire::test(ExecuteOperationForm::class, ['operationType' => $type])
        ->set('values.monto', '')
        ->call('save')
        ->assertHasErrors(['values.monto' => 'required']);

    Livewire::test(ExecuteOperationForm::class, ['operationType' => $type])
        ->set('values.monto', 'no-es-numero')
        ->call('save')
        ->assertHasErrors(['values.monto' => 'numeric']);
});

test('ejecutar una operación persiste el payload y despacha OperationExecuted', function () {
    Event::fake([OperationExecuted::class]);

    $user = User::factory()->create();
    $type = makePaymentOperation($user);
    $this->actingAs($user);

    Livewire::test(ExecuteOperationForm::class, ['operationType' => $type])
        ->set('values.monto', '150.50')
        ->set('values.detalle', 'Factura 0001')
        ->set('executed_at', '2026-09-05')
        ->set('description', 'Pago de prueba')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.index'));

    $execution = OperationExecution::first();

    expect($execution)->not->toBeNull()
        ->and($execution->payload)->toBe(['monto' => '150.50', 'detalle' => 'Factura 0001'])
        ->and($execution->executed_at->toDateString())->toBe('2026-09-05')
        ->and($execution->status)->toBe(ExecutionStatus::Pending)
        ->and($execution->user_id)->toBe($user->id);

    Event::assertDispatched(OperationExecuted::class, fn (OperationExecuted $e): bool => $e->execution->is($execution));
});

test('una variable de tipo cuenta solo acepta cuentas imputables del usuario', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $ownAccount = Account::factory()->for($user)->create(['code' => '1.1', 'is_postable' => true]);
    $foreignAccount = Account::factory()->for($other)->create(['code' => '9.9', 'is_postable' => true]);

    $type = OperationType::factory()->for($user)->create(['code' => 'cobro']);
    OperationVariable::factory()->for($type, 'operationType')->create(['name' => 'cuenta_destino', 'label' => 'Cuenta destino', 'type' => 'account']);

    $this->actingAs($user);

    Livewire::test(ExecuteOperationForm::class, ['operationType' => $type])
        ->set('values.cuenta_destino', (string) $foreignAccount->id)
        ->call('save')
        ->assertHasErrors(['values.cuenta_destino']);

    Livewire::test(ExecuteOperationForm::class, ['operationType' => $type])
        ->set('values.cuenta_destino', (string) $ownAccount->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(OperationExecution::first()->payload)->toBe(['cuenta_destino' => $ownAccount->id]);
});

test('el log de eventos lista y filtra por estado', function () {
    $user = User::factory()->create();
    $type = makePaymentOperation($user);

    OperationExecution::factory()->for($user)->create([
        'operation_type_id' => $type->id,
        'status' => ExecutionStatus::Pending,
        'description' => 'evento pendiente',
    ]);
    OperationExecution::factory()->for($user)->create([
        'operation_type_id' => $type->id,
        'status' => ExecutionStatus::Failed,
        'description' => 'evento fallido',
    ]);

    $this->actingAs($user);

    Livewire::test(EventLog::class)
        ->assertSee('evento pendiente')
        ->assertSee('evento fallido')
        ->set('status', 'failed')
        ->assertDontSee('evento pendiente')
        ->assertSee('evento fallido');
});

test('el log de eventos no muestra ejecuciones de otros usuarios', function () {
    $other = User::factory()->create();
    $foreignType = makePaymentOperation($other);
    OperationExecution::factory()->for($other)->create([
        'operation_type_id' => $foreignType->id,
        'description' => 'evento ajeno',
    ]);

    $this->actingAs(User::factory()->create());

    Livewire::test(EventLog::class)->assertDontSee('evento ajeno');
});
