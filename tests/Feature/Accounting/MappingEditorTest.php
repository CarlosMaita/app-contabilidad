<?php

use App\Models\User;
use App\Modules\Accounting\Livewire\MappingEditor;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Operations\Actions\ExecuteOperation;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Operations\Models\OperationVariable;
use Livewire\Livewire;

/**
 * @return array{type: OperationType, caja: Account, gasto: Account}
 */
function setupEditor(User $user): array
{
    $caja = Account::factory()->for($user)->create(['code' => '1.1', 'name' => 'Caja', 'type' => 'asset']);
    $gasto = Account::factory()->for($user)->create(['code' => '5.1', 'name' => 'Gastos', 'type' => 'expense']);

    $type = OperationType::factory()->for($user)->create(['name' => 'Pago', 'code' => 'pago']);
    OperationVariable::factory()->for($type, 'operationType')->create(['name' => 'monto', 'label' => 'Monto', 'type' => 'decimal']);

    return ['type' => $type, 'caja' => $caja, 'gasto' => $gasto];
}

function editorLines(array $s): array
{
    return [
        ['side' => 'debit', 'target' => 'fixed', 'account_id' => (string) $s['gasto']->id, 'account_variable' => '', 'amount_expression' => 'monto', 'memo_template' => ''],
        ['side' => 'credit', 'target' => 'fixed', 'account_id' => (string) $s['caja']->id, 'account_variable' => '', 'amount_expression' => 'monto', 'memo_template' => ''],
    ];
}

test('la página del editor de mapeo se renderiza', function () {
    $user = User::factory()->create();
    $s = setupEditor($user);

    $this->actingAs($user)
        ->get("/mappings/{$s['type']->id}")
        ->assertOk()
        ->assertSee('Mapeo contable: Pago');
});

test('guardar un mapeo crea la versión 1 activa con sus líneas', function () {
    $user = User::factory()->create();
    $s = setupEditor($user);
    $this->actingAs($user);

    Livewire::test(MappingEditor::class, ['operationType' => $s['type']])
        ->set('lines', editorLines($s))
        ->set('description_template', 'Pago por {monto}')
        ->call('save')
        ->assertHasNoErrors();

    $mapping = AccountingMapping::where('operation_type_id', $s['type']->id)->where('is_active', true)->first();

    expect($mapping)->not->toBeNull()
        ->and($mapping->version)->toBe(1)
        ->and($mapping->lines)->toHaveCount(2);
});

test('guardar de nuevo crea una versión nueva y desactiva la anterior', function () {
    $user = User::factory()->create();
    $s = setupEditor($user);
    $this->actingAs($user);

    Livewire::test(MappingEditor::class, ['operationType' => $s['type']])
        ->set('lines', editorLines($s))
        ->call('save');

    Livewire::test(MappingEditor::class, ['operationType' => $s['type']])
        ->set('lines', editorLines($s))
        ->call('save');

    $mappings = AccountingMapping::where('operation_type_id', $s['type']->id)->orderBy('version')->get();

    expect($mappings)->toHaveCount(2)
        ->and($mappings[0]->is_active)->toBeFalse()
        ->and($mappings[1]->version)->toBe(2)
        ->and($mappings[1]->is_active)->toBeTrue();
});

test('el mapeo exige al menos una línea al debe y una al haber', function () {
    $user = User::factory()->create();
    $s = setupEditor($user);
    $this->actingAs($user);

    $lines = editorLines($s);
    $lines[1]['side'] = 'debit';

    Livewire::test(MappingEditor::class, ['operationType' => $s['type']])
        ->set('lines', $lines)
        ->call('save')
        ->assertHasErrors(['lines']);
});

test('una expresión que no compila bloquea el guardado con error en la línea', function () {
    $user = User::factory()->create();
    $s = setupEditor($user);
    $this->actingAs($user);

    $lines = editorLines($s);
    $lines[0]['amount_expression'] = 'monto +* 2';

    Livewire::test(MappingEditor::class, ['operationType' => $s['type']])
        ->set('lines', $lines)
        ->call('save')
        ->assertHasErrors(['lines.0.amount_expression']);

    expect(AccountingMapping::count())->toBe(0);
});

test('procesar eventos pendientes genera los asientos retroactivamente', function () {
    $user = User::factory()->create();
    $s = setupEditor($user);
    $this->actingAs($user);

    // Dos ejecuciones sin mapeo → quedan unmapped.
    app(ExecuteOperation::class)->execute($s['type'], ['monto' => '100.00'], '2026-09-01');
    app(ExecuteOperation::class)->execute($s['type'], ['monto' => '50.00'], '2026-09-02');

    expect($s['type']->executions()->where('status', ExecutionStatus::Unmapped)->count())->toBe(2)
        ->and(JournalEntry::count())->toBe(0);

    Livewire::test(MappingEditor::class, ['operationType' => $s['type']])
        ->set('lines', editorLines($s))
        ->call('save')
        ->call('processPending');

    expect(JournalEntry::count())->toBe(2)
        ->and($s['type']->executions()->where('status', ExecutionStatus::Posted)->count())->toBe(2)
        ->and(JournalEntry::orderBy('number')->pluck('number')->all())->toBe([1, 2]);
});

test('el editor no permite cuentas de otro usuario', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $s = setupEditor($user);
    $foreign = Account::factory()->for($other)->create(['code' => '9.9']);
    $this->actingAs($user);

    $lines = editorLines($s);
    $lines[0]['account_id'] = (string) $foreign->id;

    Livewire::test(MappingEditor::class, ['operationType' => $s['type']])
        ->set('lines', $lines)
        ->call('save')
        ->assertHasErrors(['lines.0.account_id']);
});
