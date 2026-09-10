<?php

namespace App\Modules\Accounting\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Activo',
            self::Liability => 'Pasivo',
            self::Equity => 'Patrimonio',
            self::Income => 'Ingreso',
            self::Expense => 'Gasto',
        };
    }

    /**
     * Naturaleza del saldo: las cuentas de activo y gasto aumentan por el debe.
     */
    public function isDebitNature(): bool
    {
        return in_array($this, [self::Asset, self::Expense], true);
    }
}
