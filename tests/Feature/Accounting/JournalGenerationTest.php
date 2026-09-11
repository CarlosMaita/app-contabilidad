<?php

use App\Models\User;
use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\MappingLine;
use App\Modules\Accounting\Services\PostExecutionToJournal;
use App\Modules\Operations\Actions\ExecuteOperation;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Livewire\EventLog;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Operations\Models\OperationVariable;
use Livewire\Livewire;

/**
 * @return array{type: OperationType, caja: Account, gasto: Account, iva: Account}
 */
function setupOperation(User $user, array $mappingLines = []): array
{
    $caja = Account::factory()->for($user)->create(['code' => '1.1', 'name' => 'Caja', 'type' => 'asset']);
    $gasto = Account::factory()->for($user)->create(['code' => '5.1', 'name' => 'Gastos', 'type' => 'expense']);
    $iva = Account::factory()->for($user)->create(['code' => '1.2', 'name' => 'IVA crédito', 'type' => 'asset']);

    $type = OperationType::factory()->for($user)->create(['name' => 'Pago', 'code' => 'pago']);
    OperationVariable::factory()->for($type, 'operationType')->create(['name' => 'monto', 'label' => 'Monto', 'type' => 'decimal']);

    if ($mappingLines !== []) {
        $mapping = AccountingMapping::factory()->for($user)->create(['operation_type_id' => $type->id]);
        foreach ($mappingLines as $order => $line) {
            MappingLine::factory()->create([
                'accounting_mapping_id' => $mapping->id,
                'sort_order' => $order,
                ...$line,
            ]);
        }
    }

    return ['type' => $type, 'caja' => $caja, 'gasto' => $gasto, 'iva' => $iva];
}

function executePago(OperationType $type, string $monto = '150.50'): OperationExecution
{
    return app(ExecuteOperation::class)->execute($type, ['monto' => $monto], '2026-09-05', 'Pago de prueba');
}

test('ejecutar una operación con mapeo genera un asiento balanceado y marca posted', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $s = setupOperation($user);
    $mapping = AccountingMapping::factory()->for($user)->create(['operation_type_id' => $s['type']->id, 'description_template' => 'Pago por {monto}']);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $s['gasto']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Credit, 'account_id' => $s['caja']->id]);

    $execution = executePago($s['type']);

    expect($execution->fresh()->status)->toBe(ExecutionStatus::Posted);

    $entry = JournalEntry::first();
    expect($entry)->not->toBeNull()
        ->and($entry->number)->toBe(1)
        ->and($entry->description)->toBe('Pago por 150.50')
        ->and($entry->date->toDateString())->toBe('2026-09-05')
        ->and($entry->operation_execution_id)->toBe($execution->id)
        ->and($entry->status)->toBe(JournalEntryStatus::Posted);

    $lines = $entry->lines()->orderBy('id')->get();
    expect($lines)->toHaveCount(2)
        ->and((string) $lines[0]->debit)->toBe('150.50')
        ->and($lines[0]->account_id)->toBe($s['gasto']->id)
        ->and((string) $lines[1]->credit)->toBe('150.50')
        ->and($lines[1]->account_id)->toBe($s['caja']->id);
});

test('sin mapeo la ejecución queda unmapped y sin mensaje de error', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $s = setupOperation($user);
    $execution = executePago($s['type']);

    expect($execution->fresh()->status)->toBe(ExecutionStatus::Unmapped)
        ->and($execution->fresh()->error_message)->toBeNull()
        ->and(JournalEntry::count())->toBe(0);
});

test('idempotencia: procesar dos veces la misma ejecución no duplica el asiento', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $s = setupOperation($user, [
        ['side' => EntrySide::Debit, 'account_id' => null, 'amount_expression' => 'monto'],
        ['side' => EntrySide::Credit, 'account_id' => null, 'amount_expression' => 'monto'],
    ]);
    // account_id null arriba: completar con cuentas reales
    MappingLine::query()->orderBy('sort_order')->get()->each(function (MappingLine $l) use ($s): void {
        $l->update(['account_id' => $l->side === EntrySide::Debit ? $s['gasto']->id : $s['caja']->id]);
    });

    $execution = executePago($s['type']);
    expect(JournalEntry::count())->toBe(1);

    app(PostExecutionToJournal::class)->post($execution);
    app(PostExecutionToJournal::class)->post($execution);

    expect(JournalEntry::count())->toBe(1);
});

test('las expresiones resuelven IVA: neto + impuesto = total', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $s = setupOperation($user);
    $mapping = AccountingMapping::factory()->for($user)->create(['operation_type_id' => $s['type']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $s['gasto']->id, 'amount_expression' => 'round(monto / 1.21, 2)']);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $s['iva']->id, 'amount_expression' => 'monto - round(monto / 1.21, 2)']);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Credit, 'account_id' => $s['caja']->id, 'amount_expression' => 'monto']);

    executePago($s['type'], '121.00');

    $entry = JournalEntry::first();
    $byAccount = $entry->lines->keyBy('account_id');

    expect((string) $byAccount[$s['gasto']->id]->debit)->toBe('100.00')
        ->and((string) $byAccount[$s['iva']->id]->debit)->toBe('21.00')
        ->and((string) $byAccount[$s['caja']->id]->credit)->toBe('121.00');
});

test('un asiento que no balancea marca la ejecución failed con el error visible', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $s = setupOperation($user);
    $mapping = AccountingMapping::factory()->for($user)->create(['operation_type_id' => $s['type']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $s['gasto']->id, 'amount_expression' => 'monto']);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Credit, 'account_id' => $s['caja']->id, 'amount_expression' => 'monto * 2']);

    $execution = executePago($s['type']);

    expect($execution->fresh()->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->fresh()->error_message)->toContain('no balancea')
        ->and(JournalEntry::count())->toBe(0);
});

test('una expresión inválida marca la ejecución failed', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $s = setupOperation($user);
    $mapping = AccountingMapping::factory()->for($user)->create(['operation_type_id' => $s['type']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $s['gasto']->id, 'amount_expression' => 'variable_inexistente * 2']);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Credit, 'account_id' => $s['caja']->id, 'amount_expression' => 'monto']);

    $execution = executePago($s['type']);

    expect($execution->fresh()->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->fresh()->error_message)->toContain('Expresión inválida');
});

test('las funciones fuera de la whitelist no están disponibles', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $s = setupOperation($user);
    $mapping = AccountingMapping::factory()->for($user)->create(['operation_type_id' => $s['type']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $s['gasto']->id, 'amount_expression' => 'constant("PHP_INT_MAX")']);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Credit, 'account_id' => $s['caja']->id, 'amount_expression' => 'monto']);

    $execution = executePago($s['type']);

    expect($execution->fresh()->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->fresh()->error_message)->toContain('Expresión inválida');
});

test('una cuenta no imputable marca la ejecución failed', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $s = setupOperation($user);
    $rubro = Account::factory()->for($user)->create(['code' => '5', 'name' => 'Gastos (rubro)', 'type' => 'expense', 'is_postable' => false]);
    $mapping = AccountingMapping::factory()->for($user)->create(['operation_type_id' => $s['type']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $rubro->id, 'amount_expression' => 'monto']);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Credit, 'account_id' => $s['caja']->id, 'amount_expression' => 'monto']);

    $execution = executePago($s['type']);

    expect($execution->fresh()->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->fresh()->error_message)->toContain('no es imputable');
});

test('las líneas con monto cero se descartan', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $s = setupOperation($user);
    $mapping = AccountingMapping::factory()->for($user)->create(['operation_type_id' => $s['type']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $s['gasto']->id, 'amount_expression' => 'monto']);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $s['iva']->id, 'amount_expression' => 'monto * 0']);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Credit, 'account_id' => $s['caja']->id, 'amount_expression' => 'monto']);

    executePago($s['type']);

    expect(JournalEntry::first()->lines)->toHaveCount(2);
});

test('la cuenta de una línea puede venir de una variable del payload', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $s = setupOperation($user);
    OperationVariable::factory()->for($s['type'], 'operationType')->create(['name' => 'cuenta_origen', 'label' => 'Cuenta de origen', 'type' => 'account', 'sort_order' => 1]);

    $mapping = AccountingMapping::factory()->for($user)->create(['operation_type_id' => $s['type']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $s['gasto']->id, 'amount_expression' => 'monto']);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Credit, 'account_id' => null, 'account_variable' => 'cuenta_origen', 'amount_expression' => 'monto']);

    app(ExecuteOperation::class)->execute($s['type'], ['monto' => '80.00', 'cuenta_origen' => $s['caja']->id], '2026-09-06');

    $credit = JournalEntry::first()->lines->firstWhere('account_id', $s['caja']->id);
    expect((string) $credit->credit)->toBe('80.00');
});

test('la numeración de asientos es correlativa por usuario', function () {
    $userA = User::factory()->create();
    $this->actingAs($userA);
    $sA = setupOperation($userA);
    $mappingA = AccountingMapping::factory()->for($userA)->create(['operation_type_id' => $sA['type']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mappingA->id, 'side' => EntrySide::Debit, 'account_id' => $sA['gasto']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mappingA->id, 'side' => EntrySide::Credit, 'account_id' => $sA['caja']->id]);

    executePago($sA['type'], '10.00');
    executePago($sA['type'], '20.00');

    $userB = User::factory()->create();
    $this->actingAs($userB);
    $sB = setupOperation($userB);
    $mappingB = AccountingMapping::factory()->for($userB)->create(['operation_type_id' => $sB['type']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mappingB->id, 'side' => EntrySide::Debit, 'account_id' => $sB['gasto']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mappingB->id, 'side' => EntrySide::Credit, 'account_id' => $sB['caja']->id]);

    executePago($sB['type'], '30.00');

    $numbersA = JournalEntry::withoutGlobalScopes()->where('user_id', $userA->id)->orderBy('number')->pluck('number')->all();
    $numbersB = JournalEntry::withoutGlobalScopes()->where('user_id', $userB->id)->pluck('number')->all();

    expect($numbersA)->toBe([1, 2])
        ->and($numbersB)->toBe([1]);
});

test('anular una ejecución contabilizada genera un contra-asiento y marca voided', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $s = setupOperation($user);
    $mapping = AccountingMapping::factory()->for($user)->create(['operation_type_id' => $s['type']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Debit, 'account_id' => $s['gasto']->id]);
    MappingLine::factory()->create(['accounting_mapping_id' => $mapping->id, 'side' => EntrySide::Credit, 'account_id' => $s['caja']->id]);

    $execution = executePago($s['type']);
    $original = JournalEntry::first();

    Livewire::test(EventLog::class)->call('void', $execution->id);

    expect($execution->fresh()->status)->toBe(ExecutionStatus::Voided)
        ->and($original->fresh()->status)->toBe(JournalEntryStatus::Reversed);

    $reversal = JournalEntry::where('reverses_entry_id', $original->id)->first();
    expect($reversal)->not->toBeNull()
        ->and($reversal->number)->toBe(2)
        ->and((string) $reversal->lines->firstWhere('account_id', $s['caja']->id)->debit)->toBe('150.50')
        ->and((string) $reversal->lines->firstWhere('account_id', $s['gasto']->id)->credit)->toBe('150.50');
});
