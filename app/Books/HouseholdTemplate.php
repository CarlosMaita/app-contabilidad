<?php

namespace App\Books;

use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Accounting\Models\MappingLine;
use App\Modules\Accounting\Services\DefaultChartOfAccounts;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Operations\Models\OperationVariable;
use App\Modules\Shared\Books\Book;

/**
 * Plantilla «Hogar»: plan de cuentas para gastos comunes de una familia
 * (fondo compartido, aportes por miembro, gastos del hogar) y operaciones
 * listas para usar con su mapeo contable.
 */
class HouseholdTemplate
{
    /** Principal donde cuelga el auxiliar de aportes de cada miembro. */
    public const CONTRIBUTIONS = '3.1';

    /** Cuenta de efectivo principal del fondo común. */
    public const FUND = '1.1.01';

    /** @var array<int, array{0: string, 1: string, 2: string, 3: ?string, 4: ?string}> */
    public const ACCOUNTS = [
        ['1', 'Activo', 'asset', null, null],
        ['1.1', 'Disponible', 'asset', '1', 'cash'],
        ['1.1.01', 'Binance familiar', 'asset', '1.1', 'cash'],
        ['1.1.02', 'Efectivo', 'asset', '1.1', 'cash'],
        ['1.2', 'Bienes del hogar', 'asset', '1', 'investing'],
        ['1.2.01', 'Muebles y equipamiento', 'asset', '1.2', 'investing'],

        ['2', 'Pasivo', 'liability', null, null],
        ['2.1', 'Deudas del hogar', 'liability', '2', 'operating'],
        ['2.1.01', 'Cuentas por pagar', 'liability', '2.1', 'operating'],
        ['2.1.02', 'Tarjetas de crédito', 'liability', '2.1', 'operating'],

        ['3', 'Patrimonio familiar', 'equity', null, 'financing'],
        ['3.1', 'Aportes de los miembros', 'equity', '3', 'financing'],
        ['3.2', 'Resultados acumulados', 'equity', '3', 'financing'],

        ['4.1', 'Rendimientos e intereses', 'income', null, 'financial_income'],
        ['4.1.01', 'Rendimientos Binance', 'income', '4.1', 'financial_income'],
        ['4.2', 'Otros ingresos', 'income', null, 'other_income'],
        ['4.2.01', 'Otros ingresos del hogar', 'income', '4.2', 'other_income'],

        ['6.1', 'Servicios', 'expense', null, 'operating_expense'],
        ['6.1.01', 'Electricidad', 'expense', '6.1', 'operating_expense'],
        ['6.1.02', 'Internet', 'expense', '6.1', 'operating_expense'],
        ['6.1.03', 'Agua y gas', 'expense', '6.1', 'operating_expense'],
        ['6.1.04', 'Teléfono y celulares', 'expense', '6.1', 'operating_expense'],
        ['6.2', 'Alimentación', 'expense', null, 'operating_expense'],
        ['6.2.01', 'Supermercado', 'expense', '6.2', 'operating_expense'],
        ['6.2.02', 'Comidas fuera y delivery', 'expense', '6.2', 'operating_expense'],
        ['6.3', 'Vivienda', 'expense', null, 'operating_expense'],
        ['6.3.01', 'Remodelaciones', 'expense', '6.3', 'operating_expense'],
        ['6.3.02', 'Mantenimiento y reparaciones', 'expense', '6.3', 'operating_expense'],
        ['6.3.03', 'Alquiler o expensas', 'expense', '6.3', 'operating_expense'],
        ['6.4', 'Transporte', 'expense', null, 'operating_expense'],
        ['6.4.01', 'Combustible y movilidad', 'expense', '6.4', 'operating_expense'],
        ['6.5', 'Salud', 'expense', null, 'operating_expense'],
        ['6.5.01', 'Salud y farmacia', 'expense', '6.5', 'operating_expense'],
        ['6.6', 'Educación', 'expense', null, 'operating_expense'],
        ['6.6.01', 'Educación y cursos', 'expense', '6.6', 'operating_expense'],
        ['6.7', 'Ocio y otros', 'expense', null, 'operating_expense'],
        ['6.7.01', 'Entretenimiento', 'expense', '6.7', 'operating_expense'],
        ['6.7.02', 'Otros gastos', 'expense', '6.7', 'operating_expense'],
        ['6.8', 'Comisiones y cargos', 'expense', null, 'financial_expense'],
        ['6.8.01', 'Comisiones Binance y bancarias', 'expense', '6.8', 'financial_expense'],
    ];

    public static function seed(Book $book): void
    {
        DefaultChartOfAccounts::seedPlan($book, self::ACCOUNTS);

        $accounts = Account::withoutGlobalScopes()->where('book_id', $book->id)->pluck('id', 'code');

        self::operation($book, $accounts->all(), 'pagar_gasto', 'Pagar gasto común', 'Gasto pagado con el fondo común (Binance familiar).', [
            ['gasto', 'Gasto', 'account'], ['monto', 'Monto', 'decimal'], ['detalle', 'Detalle', 'string'],
        ], '{detalle}', [
            [EntrySide::Debit, null, 'gasto', 'monto', '{detalle}'],
            [EntrySide::Credit, self::FUND, null, 'monto', null],
        ]);

        self::operation($book, $accounts->all(), 'aporte_miembro', 'Aporte de miembro', 'Un miembro deposita en el fondo común.', [
            ['miembro', 'Aportes de (cuenta del miembro)', 'account'], ['monto', 'Monto', 'decimal'],
        ], null, [
            [EntrySide::Debit, self::FUND, null, 'monto', null],
            [EntrySide::Credit, null, 'miembro', 'monto', null],
        ]);

        self::operation($book, $accounts->all(), 'rendimiento', 'Rendimiento Binance', 'Intereses o rendimientos acreditados en el fondo.', [
            ['monto', 'Monto', 'decimal'],
        ], 'Rendimiento acreditado', [
            [EntrySide::Debit, self::FUND, null, 'monto', null],
            [EntrySide::Credit, '4.1.01', null, 'monto', null],
        ]);
    }

    /**
     * @param  array<string, int>  $accounts  código => id
     * @param  array<int, array{0: string, 1: string, 2: string}>  $variables
     * @param  array<int, array{0: EntrySide, 1: ?string, 2: ?string, 3: string, 4: ?string}>  $lines
     */
    private static function operation(Book $book, array $accounts, string $code, string $name, string $description, array $variables, ?string $template, array $lines): void
    {
        $type = OperationType::withoutGlobalScopes()->create([
            'book_id' => $book->id,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'is_active' => true,
        ]);

        foreach ($variables as $order => [$varName, $label, $varType]) {
            OperationVariable::create([
                'operation_type_id' => $type->id,
                'name' => $varName,
                'label' => $label,
                'type' => $varType,
                'is_required' => true,
                'sort_order' => $order,
            ]);
        }

        $mapping = AccountingMapping::withoutGlobalScopes()->create([
            'book_id' => $book->id,
            'operation_type_id' => $type->id,
            'version' => 1,
            'is_active' => true,
            'description_template' => $template,
        ]);

        foreach ($lines as $order => [$side, $accountCode, $variable, $expression, $memo]) {
            MappingLine::create([
                'accounting_mapping_id' => $mapping->id,
                'side' => $side,
                'account_id' => $accountCode !== null ? $accounts[$accountCode] : null,
                'account_variable' => $variable,
                'amount_expression' => $expression,
                'memo_template' => $memo,
                'sort_order' => $order,
            ]);
        }
    }
}
