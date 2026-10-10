<?php

namespace App\Demo;

use App\Models\User;
use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Accounting\Models\MappingLine;
use App\Modules\Accounting\Services\JournalEntryBuilder;
use App\Modules\Operations\Actions\ExecuteOperation;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Operations\Models\OperationVariable;
use App\Modules\Shared\Contracts\VoidsOperationExecutions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Datos de ejemplo para recorrer la app: auxiliares de clientes, tipos de
 * operación con su mapeo, ~3 meses de ejecuciones (que generan asientos por
 * el flujo normal de eventos), asientos manuales, una anulación y una
 * operación sin mapeo. Requiere el plan de cuentas base ya cargado.
 *
 * Las fechas son relativas a hoy (mes actual y los dos anteriores) para que
 * el dashboard del mes siempre tenga movimientos.
 */
class DemoData
{
    /** @var array<string, Account> */
    private array $accounts = [];

    public function __construct(
        private readonly ExecuteOperation $execute,
        private readonly JournalEntryBuilder $journal,
        private readonly VoidsOperationExecutions $voider,
    ) {}

    public function populate(User $user): void
    {
        // Las acciones de Operations y Accounting trabajan sobre el usuario
        // autenticado (scope y autocompletado de user_id).
        $previous = Auth::user();
        Auth::setUser($user);

        try {
            $this->loadAccounts($user);
            $this->createAuxiliaries($user);
            $types = $this->createOperationTypes($user);
            $this->postMovements($user, $types);
        } finally {
            $previous !== null ? Auth::setUser($previous) : Auth::forgetUser();
        }
    }

    private function loadAccounts(User $user): void
    {
        $this->accounts = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('code')
            ->all();
    }

    private function account(string $code): Account
    {
        return $this->accounts[$code];
    }

    private function createAuxiliaries(User $user): void
    {
        $clients = $this->account('1.1.03');

        foreach ([
            ['1.1.03.01', 'CxC de Luis Pérez'],
            ['1.1.03.02', 'CxC de Andrea Gómez'],
            ['1.1.03.03', 'CxC de Estudio Norte SRL'],
        ] as [$code, $name]) {
            $this->accounts[$code] = Account::create([
                'user_id' => $user->id,
                'code' => $code,
                'name' => $name,
                'type' => $clients->type,
                'parent_id' => $clients->id,
                'is_postable' => true,
                'is_active' => true,
                'is_auxiliary' => true,
            ]);
        }

        // La principal consolida: se imputa a través de sus auxiliares.
        $clients->update(['is_postable' => false]);
    }

    /**
     * @return array<string, OperationType>
     */
    private function createOperationTypes(User $user): array
    {
        $definitions = [
            'venta_credito' => [
                'name' => 'Venta a crédito',
                'description' => 'Factura a un cliente con IVA 21 %.',
                'variables' => [['cliente', 'Cliente', 'account'], ['neto', 'Importe neto', 'decimal']],
                'template' => null,
                'lines' => [
                    [EntrySide::Debit, null, 'cliente', 'round(neto * 1.21, 2)', 'Factura con IVA'],
                    [EntrySide::Credit, '4.1.01', null, 'neto', null],
                    [EntrySide::Credit, '2.1.02', null, 'round(neto * 0.21, 2)', 'IVA 21 %'],
                ],
            ],
            'cobro_cliente' => [
                'name' => 'Cobro de cliente',
                'description' => 'Transferencia recibida de un cliente.',
                'variables' => [['cliente', 'Cliente', 'account'], ['monto', 'Monto cobrado', 'decimal']],
                'template' => null,
                'lines' => [
                    [EntrySide::Debit, '1.1.02', null, 'monto', null],
                    [EntrySide::Credit, null, 'cliente', 'monto', null],
                ],
            ],
            'compra_insumos' => [
                'name' => 'Compra de insumos',
                'description' => 'Compra a proveedor en cuenta corriente con IVA 21 %.',
                'variables' => [['neto', 'Importe neto', 'decimal']],
                'template' => null,
                'lines' => [
                    [EntrySide::Debit, '5.1.01', null, 'neto', null],
                    [EntrySide::Debit, '1.1.04', null, 'round(neto * 0.21, 2)', 'IVA 21 %'],
                    [EntrySide::Credit, '2.1.01', null, 'round(neto * 1.21, 2)', null],
                ],
            ],
            'pago_proveedor' => [
                'name' => 'Pago a proveedor',
                'description' => 'Cancelación de deuda con proveedores por banco.',
                'variables' => [['monto', 'Monto pagado', 'decimal']],
                'template' => null,
                'lines' => [
                    [EntrySide::Debit, '2.1.01', null, 'monto', null],
                    [EntrySide::Credit, '1.1.02', null, 'monto', null],
                ],
            ],
            'pago_sueldos' => [
                'name' => 'Pago de sueldos',
                'description' => 'Liquidación mensual pagada por banco.',
                'variables' => [['monto', 'Total a pagar', 'decimal']],
                'template' => null,
                'lines' => [
                    [EntrySide::Debit, '6.1.02', null, 'monto', null],
                    [EntrySide::Credit, '1.1.02', null, 'monto', null],
                ],
            ],
            'gasto_caja' => [
                'name' => 'Gasto administrativo en efectivo',
                'description' => 'Gastos menores pagados con caja chica.',
                'variables' => [['monto', 'Monto', 'decimal'], ['detalle', 'Detalle', 'string']],
                'template' => 'Gasto administrativo: {detalle}',
                'lines' => [
                    [EntrySide::Debit, '6.1.01', null, 'monto', '{detalle}'],
                    [EntrySide::Credit, '1.1.01', null, 'monto', null],
                ],
            ],
            // Sin mapeo a propósito: muestra el aviso de «mapeo pendiente».
            'retiro_socio' => [
                'name' => 'Retiro de socio',
                'description' => 'Sin mapeo contable: configurarlo desde «Mapeo contable».',
                'variables' => [['monto', 'Monto retirado', 'decimal']],
                'template' => null,
                'lines' => [],
            ],
        ];

        $types = [];

        foreach ($definitions as $code => $definition) {
            $type = OperationType::create([
                'user_id' => $user->id,
                'name' => $definition['name'],
                'code' => $code,
                'description' => $definition['description'],
                'is_active' => true,
            ]);

            foreach ($definition['variables'] as $order => [$name, $label, $varType]) {
                OperationVariable::create([
                    'operation_type_id' => $type->id,
                    'name' => $name,
                    'label' => $label,
                    'type' => $varType,
                    'is_required' => true,
                    'sort_order' => $order,
                ]);
            }

            if ($definition['lines'] !== []) {
                $mapping = AccountingMapping::create([
                    'user_id' => $user->id,
                    'operation_type_id' => $type->id,
                    'version' => 1,
                    'is_active' => true,
                    'description_template' => $definition['template'],
                ]);

                foreach ($definition['lines'] as $order => [$side, $accountCode, $variable, $expression, $memo]) {
                    MappingLine::create([
                        'accounting_mapping_id' => $mapping->id,
                        'side' => $side,
                        'account_id' => $accountCode !== null ? $this->account($accountCode)->id : null,
                        'account_variable' => $variable,
                        'amount_expression' => $expression,
                        'memo_template' => $memo,
                        'sort_order' => $order,
                    ]);
                }
            }

            $types[$code] = $type;
        }

        return $types;
    }

    /**
     * @param  array<string, OperationType>  $types
     */
    private function postMovements(User $user, array $types): void
    {
        $luis = $this->account('1.1.03.01')->id;
        $andrea = $this->account('1.1.03.02')->id;
        $norte = $this->account('1.1.03.03')->id;

        $operation = fn (string $code, array $payload, string $description) => fn (string $date) => $this->execute->execute($types[$code], $payload, $date, $description);
        $manual = fn (string $description, array $debits, array $credits) => fn (string $date) => $this->manual($user, $date, $description, $debits, $credits);

        // [hace N meses, día, paso]. Se registran en orden de fecha para que
        // la numeración correlativa siga la cronología.
        $steps = [
            [2, 1, $manual('Aporte de capital inicial', [['1.1.02', '2500000.00'], ['1.1.01', '150000.00']], [['3.1', '2650000.00']])],
            [2, 3, $manual('Compra de camioneta de reparto', [['1.2.01.01', '1800000.00']], [['1.1.02', '1800000.00']])],
            [2, 4, $operation('compra_insumos', ['neto' => '420000'], 'Insumos para el primer pedido')],
            [2, 8, $operation('venta_credito', ['cliente' => $luis, 'neto' => '650000'], 'Venta a Luis Pérez — factura A-0001')],
            [2, 12, $operation('venta_credito', ['cliente' => $norte, 'neto' => '980000'], 'Venta a Estudio Norte — factura A-0002')],
            [2, 15, $operation('gasto_caja', ['monto' => '18500', 'detalle' => 'librería y papelería'], 'Compra en librería')],
            [2, 20, $operation('cobro_cliente', ['cliente' => $luis, 'monto' => '786500'], 'Cobro a Luis Pérez — factura A-0001')],
            [2, 25, $operation('pago_proveedor', ['monto' => '508200'], 'Pago a proveedor de insumos')],
            [2, 28, $operation('pago_sueldos', ['monto' => '720000'], 'Sueldos del mes')],
            [2, 31, $manual('Depreciación mensual de la camioneta', [['6.2.01', '30000.00']], [['1.2.02.01', '30000.00']])],
            [2, 31, $manual('Comisiones y gastos bancarios', [['6.3.02', '8900.00']], [['1.1.02', '8900.00']])],
            [1, 3, $operation('compra_insumos', ['neto' => '510000'], 'Insumos del mes')],
            [1, 5, $manual('Préstamo bancario a 12 meses', [['1.1.02', '1000000.00']], [['2.1.03', '1000000.00']])],
            [1, 7, $operation('venta_credito', ['cliente' => $andrea, 'neto' => '430000'], 'Venta a Andrea Gómez — factura A-0003')],
            [1, 10, $operation('cobro_cliente', ['cliente' => $norte, 'monto' => '600000'], 'Cobro parcial a Estudio Norte — factura A-0002')],
            [1, 14, $operation('venta_credito', ['cliente' => $luis, 'neto' => '1150000'], 'Venta a Luis Pérez — factura A-0004')],
            [1, 15, function (string $date) use ($types, $andrea): void {
                // Venta cargada por error y anulada: genera el contra-asiento.
                $wrong = $this->execute->execute($types['venta_credito'], ['cliente' => $andrea, 'neto' => '99000'], $date, 'Factura duplicada por error');
                $this->voider->voidExecution($wrong->id);
            }],
            [1, 18, $operation('gasto_caja', ['monto' => '32000', 'detalle' => 'mantenimiento de oficina'], 'Mantenimiento de oficina')],
            [1, 22, $operation('pago_proveedor', ['monto' => '400000'], 'Pago a cuenta a proveedor')],
            [1, 27, $operation('cobro_cliente', ['cliente' => $luis, 'monto' => '900000'], 'Cobro a cuenta a Luis Pérez — factura A-0004')],
            [1, 28, $operation('pago_sueldos', ['monto' => '740000'], 'Sueldos del mes')],
            [1, 31, $manual('Depreciación mensual de la camioneta', [['6.2.01', '30000.00']], [['1.2.02.01', '30000.00']])],
            [1, 31, $manual('Comisiones y gastos bancarios', [['6.3.02', '8900.00']], [['1.1.02', '8900.00']])],
            [1, 31, $manual('Intereses del préstamo bancario', [['6.3.01', '45000.00']], [['1.1.02', '45000.00']])],
            [0, 2, $operation('venta_credito', ['cliente' => $norte, 'neto' => '760000'], 'Venta a Estudio Norte — factura A-0005')],
            [0, 3, $operation('compra_insumos', ['neto' => '280000'], 'Insumos del mes')],
            [0, 5, $operation('cobro_cliente', ['cliente' => $andrea, 'monto' => '520300'], 'Cobro a Andrea Gómez — factura A-0003')],
            [0, 6, $operation('gasto_caja', ['monto' => '12400', 'detalle' => 'café y artículos de limpieza'], 'Compras para la oficina')],
            [0, 7, $operation('retiro_socio', ['monto' => '150000'], 'Retiro de socio (sin mapeo contable)')],
        ];

        $dated = array_map(fn (array $step): array => [$this->date($step[0], $step[1]), $step[2]], $steps);
        usort($dated, fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        foreach ($dated as [$date, $step]) {
            $step($date);
        }
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $debits  [código, monto]
     * @param  array<int, array{0: string, 1: string}>  $credits
     */
    private function manual(User $user, string $date, string $description, array $debits, array $credits): void
    {
        $lines = [];

        foreach ($debits as [$code, $amount]) {
            $lines[] = ['side' => EntrySide::Debit, 'account_id' => $this->account($code)->id, 'amount' => $amount, 'memo' => null];
        }
        foreach ($credits as [$code, $amount]) {
            $lines[] = ['side' => EntrySide::Credit, 'account_id' => $this->account($code)->id, 'amount' => $amount, 'memo' => null];
        }

        $this->journal->post(userId: $user->id, date: $date, description: $description, lines: $lines);
    }

    /** Día del mes de hace N meses, sin pasarse del fin de mes ni de hoy. */
    private function date(int $monthsAgo, int $day): string
    {
        $month = Carbon::today()->startOfMonth()->subMonths($monthsAgo);
        $date = $month->copy()->day(min($day, $month->daysInMonth));

        return $date->min(Carbon::today())->toDateString();
    }
}
