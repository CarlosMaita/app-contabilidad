<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\DB;

/**
 * Plan de cuentas base genérico que se precarga al registrarse un usuario.
 *
 * Las cuentas de resultado (4.x, 5.x, 6.x) llevan su sección del P&L para
 * alimentar la cascada Bruta → EBITDA → EBIT → EBT → Neto. Cada rama de
 * primer nivel pertenece a una sola sección; las hijas la heredan.
 */
class DefaultChartOfAccounts
{
    /**
     * [code, name, type, parentCode, clasificación]. La clasificación es la
     * sección del P&L en cuentas de resultado y la actividad del flujo de
     * efectivo en cuentas de balance (null = hereda del padre o del tipo).
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: ?string, 4: ?string}>
     */
    private const ACCOUNTS = [
        // Activo
        ['1', 'Activo', 'asset', null, null],
        ['1.1', 'Activo corriente', 'asset', '1', null],
        ['1.1.01', 'Caja', 'asset', '1.1', 'cash'],
        ['1.1.02', 'Bancos', 'asset', '1.1', 'cash'],
        ['1.1.03', 'Clientes por cobrar', 'asset', '1.1', 'operating'],
        ['1.1.04', 'IVA crédito fiscal', 'asset', '1.1', 'operating'],
        ['1.2', 'Activo no corriente', 'asset', '1', 'investing'],
        ['1.2.01', 'Bienes de uso', 'asset', '1.2', 'investing'],
        ['1.2.01.01', 'Vehículos', 'asset', '1.2.01', 'investing'],
        ['1.2.01.02', 'Equipos e instalaciones', 'asset', '1.2.01', 'investing'],
        ['1.2.01.03', 'Intangibles', 'asset', '1.2.01', 'investing'],
        // Contra-activo: acumula con saldo acreedor y resta del activo.
        // En el flujo es el ajuste de la depreciación (gasto sin salida de caja).
        ['1.2.02', 'Depreciación acumulada', 'asset', '1.2', 'non_cash'],
        ['1.2.02.01', 'Depreciación acumulada de vehículos', 'asset', '1.2.02', 'non_cash'],
        ['1.2.02.02', 'Depreciación acumulada de equipos e instalaciones', 'asset', '1.2.02', 'non_cash'],
        ['1.2.02.03', 'Amortización acumulada de intangibles', 'asset', '1.2.02', 'non_cash'],
        // Pasivo
        ['2', 'Pasivo', 'liability', null, null],
        ['2.1', 'Pasivo corriente', 'liability', '2', null],
        ['2.1.01', 'Proveedores por pagar', 'liability', '2.1', 'operating'],
        ['2.1.02', 'IVA débito fiscal', 'liability', '2.1', 'operating'],
        ['2.1.03', 'Préstamos por pagar', 'liability', '2.1', 'financing'],
        ['2.1.04', 'Impuesto a las ganancias por pagar', 'liability', '2.1', 'operating'],
        // Patrimonio
        ['3', 'Patrimonio', 'equity', null, 'financing'],
        ['3.1', 'Capital', 'equity', '3', 'financing'],
        ['3.2', 'Resultados acumulados', 'equity', '3', 'financing'],
        // Ingresos: cada rama raíz es una sección del P&L
        ['4.1', 'Ingresos operativos', 'income', null, 'operating_income'],
        ['4.1.01', 'Ventas', 'income', '4.1', 'operating_income'],
        ['4.1.02', 'Descuentos y devoluciones', 'income', '4.1', 'operating_income'],
        ['4.2', 'Ingresos financieros', 'income', null, 'financial_income'],
        ['4.2.01', 'Intereses ganados', 'income', '4.2', 'financial_income'],
        ['4.2.02', 'Diferencia de cambio positiva', 'income', '4.2', 'financial_income'],
        ['4.3', 'Otros ingresos', 'income', null, 'other_income'],
        ['4.3.01', 'Otros ingresos no operativos', 'income', '4.3', 'other_income'],
        // Costos (COGS)
        ['5.1', 'Costo de ingresos', 'expense', null, 'cogs'],
        ['5.1.01', 'Costo de materiales', 'expense', '5.1', 'cogs'],
        ['5.1.02', 'Mano de obra directa', 'expense', '5.1', 'cogs'],
        // Gastos
        ['6.1', 'Gastos operativos', 'expense', null, 'operating_expense'],
        ['6.1.01', 'Gastos administrativos', 'expense', '6.1', 'operating_expense'],
        ['6.1.02', 'Sueldos y cargas', 'expense', '6.1', 'operating_expense'],
        ['6.1.03', 'Impuestos y tasas', 'expense', '6.1', 'operating_expense'],
        ['6.2', 'Depreciación y amortización', 'expense', null, 'depreciation'],
        ['6.2.01', 'Depreciación de vehículos', 'expense', '6.2', 'depreciation'],
        ['6.2.02', 'Depreciación de equipos e instalaciones', 'expense', '6.2', 'depreciation'],
        ['6.2.03', 'Amortización de intangibles', 'expense', '6.2', 'depreciation'],
        ['6.3', 'Gastos financieros', 'expense', null, 'financial_expense'],
        ['6.3.01', 'Intereses pagados', 'expense', '6.3', 'financial_expense'],
        ['6.3.02', 'Comisiones bancarias', 'expense', '6.3', 'financial_expense'],
        ['6.3.03', 'Diferencia de cambio negativa', 'expense', '6.3', 'financial_expense'],
        ['6.4', 'Impuesto a las ganancias', 'expense', null, 'tax'],
        ['6.4.01', 'Impuesto a las ganancias', 'expense', '6.4', 'tax'],
        ['6.5', 'Otros egresos', 'expense', null, 'other_expense'],
        ['6.5.01', 'Otros egresos no operativos', 'expense', '6.5', 'other_expense'],
    ];

    public static function seedFor(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $parentCodes = array_filter(array_column(self::ACCOUNTS, 3));
            $created = [];

            foreach (self::ACCOUNTS as [$code, $name, $type, $parentCode, $section]) {
                $created[$code] = Account::withoutGlobalScopes()->create([
                    'user_id' => $user->id,
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    ...self::classification($type, $section),
                    'parent_id' => $parentCode !== null ? $created[$parentCode]->id : null,
                    'is_postable' => ! in_array($code, $parentCodes, true),
                    'is_active' => true,
                ]);
            }
        });
    }

    /**
     * Asigna la actividad del flujo de efectivo a las cuentas de balance
     * de un usuario existente que coinciden con el plan base (mismo código,
     * nombre y tipo) y todavía no la tienen. No pisa clasificaciones hechas
     * a mano ni toca cuentas con otra semántica.
     *
     * @return int cantidad de cuentas clasificadas
     */
    public static function classifyFor(User $user): int
    {
        $existing = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereNull('cash_flow_section')
            ->get()
            ->keyBy('code');

        $classified = 0;

        foreach (self::ACCOUNTS as [$code, $name, $type, , $section]) {
            $current = $existing->get($code);
            $cashFlow = self::classification($type, $section)['cash_flow_section'];

            if ($current === null || $cashFlow === null
                || $current->name !== $name || $current->type->value !== $type) {
                continue;
            }

            $current->update(['cash_flow_section' => $cashFlow]);
            $classified++;
        }

        return $classified;
    }

    /**
     * @return array{pnl_section: ?string, cash_flow_section: ?string}
     */
    private static function classification(string $type, ?string $section): array
    {
        $isResult = in_array($type, ['income', 'expense'], true);

        return [
            'pnl_section' => $isResult ? $section : null,
            'cash_flow_section' => $isResult ? null : $section,
        ];
    }

    /**
     * Completa el plan de un usuario existente con las cuentas del plan
     * base que le falten. Idempotente y conservador:
     *
     * - Una cuenta existente con el mismo código y nombre se reutiliza
     *   como padre de las nuevas.
     * - Si el código existe con otro nombre (plan viejo con otra
     *   estructura), esa rama se salta entera para no mezclar semánticas.
     * - Un padre que recibe hijas deja de ser imputable.
     *
     * @return int cantidad de cuentas agregadas
     */
    public static function syncFor(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            $parentCodes = array_filter(array_column(self::ACCOUNTS, 3));
            $existing = Account::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->get()
                ->keyBy('code');

            $resolved = [];
            $blocked = [];
            $added = 0;

            foreach (self::ACCOUNTS as [$code, $name, $type, $parentCode, $section]) {
                if ($parentCode !== null && isset($blocked[$parentCode])) {
                    $blocked[$code] = true;

                    continue;
                }

                $current = $existing->get($code);

                if ($current !== null) {
                    if ($current->name !== $name || $current->type->value !== $type) {
                        $blocked[$code] = true;

                        continue;
                    }

                    $resolved[$code] = $current;

                    continue;
                }

                $parent = $parentCode !== null ? $resolved[$parentCode] : null;

                $resolved[$code] = Account::withoutGlobalScopes()->create([
                    'user_id' => $user->id,
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    ...self::classification($type, $section),
                    'parent_id' => $parent?->id,
                    'is_postable' => ! in_array($code, $parentCodes, true),
                    'is_active' => true,
                ]);
                $added++;

                // Solo las hojas reciben apuntes.
                if ($parent !== null && $parent->is_postable) {
                    $parent->update(['is_postable' => false]);
                }
            }

            return $added;
        });
    }
}
