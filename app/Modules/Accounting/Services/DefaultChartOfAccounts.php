<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\DB;

/**
 * Plan de cuentas base genérico que se precarga al registrarse un usuario.
 */
class DefaultChartOfAccounts
{
    /** @var array<int, array{0: string, 1: string, 2: string, 3: ?string}> [code, name, type, parentCode] */
    private const ACCOUNTS = [
        ['1', 'Activo', 'asset', null],
        ['1.1', 'Activo corriente', 'asset', '1'],
        ['1.1.01', 'Caja', 'asset', '1.1'],
        ['1.1.02', 'Bancos', 'asset', '1.1'],
        ['1.1.03', 'Clientes por cobrar', 'asset', '1.1'],
        ['1.1.04', 'IVA crédito fiscal', 'asset', '1.1'],
        ['2', 'Pasivo', 'liability', null],
        ['2.1', 'Pasivo corriente', 'liability', '2'],
        ['2.1.01', 'Proveedores por pagar', 'liability', '2.1'],
        ['2.1.02', 'IVA débito fiscal', 'liability', '2.1'],
        ['2.1.03', 'Préstamos por pagar', 'liability', '2.1'],
        ['3', 'Patrimonio', 'equity', null],
        ['3.1', 'Capital', 'equity', '3'],
        ['3.2', 'Resultados acumulados', 'equity', '3'],
        ['4', 'Ingresos', 'income', null],
        ['4.1', 'Ventas', 'income', '4'],
        ['4.2', 'Otros ingresos', 'income', '4'],
        ['5', 'Gastos', 'expense', null],
        ['5.1', 'Gastos operativos', 'expense', '5'],
        ['5.2', 'Sueldos y cargas', 'expense', '5'],
        ['5.3', 'Impuestos y tasas', 'expense', '5'],
    ];

    public static function seedFor(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $parentCodes = array_filter(array_column(self::ACCOUNTS, 3));
            $created = [];

            foreach (self::ACCOUNTS as [$code, $name, $type, $parentCode]) {
                $created[$code] = Account::withoutGlobalScopes()->create([
                    'user_id' => $user->id,
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'parent_id' => $parentCode !== null ? $created[$parentCode]->id : null,
                    'is_postable' => ! in_array($code, $parentCodes, true),
                    'is_active' => true,
                ]);
            }
        });
    }
}
