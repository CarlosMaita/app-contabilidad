<?php

namespace App\Modules\Accounting\Enums;

/**
 * Sección del estado de resultados (P&L multi-step) a la que aporta una
 * cuenta de resultado. Define la cascada:
 *
 *   Ingresos operativos − Costo de ingresos          = Utilidad Bruta
 *   − Gastos operativos                              = EBITDA
 *   − Depreciación y amortización                    = EBIT
 *   ± Resultado financiero ± Otros no operativos     = Resultado antes de impuestos
 *   − Impuesto a las ganancias                       = Resultado Neto
 */
enum PnlSection: string
{
    case OperatingIncome = 'operating_income';
    case FinancialIncome = 'financial_income';
    case OtherIncome = 'other_income';
    case Cogs = 'cogs';
    case OperatingExpense = 'operating_expense';
    case Depreciation = 'depreciation';
    case FinancialExpense = 'financial_expense';
    case Tax = 'tax';
    case OtherExpense = 'other_expense';

    public function label(): string
    {
        return match ($this) {
            self::OperatingIncome => 'Ingresos operativos',
            self::FinancialIncome => 'Ingresos financieros',
            self::OtherIncome => 'Otros ingresos',
            self::Cogs => 'Costo de ingresos',
            self::OperatingExpense => 'Gastos operativos',
            self::Depreciation => 'Depreciación y amortización',
            self::FinancialExpense => 'Gastos financieros',
            self::Tax => 'Impuesto a las ganancias',
            self::OtherExpense => 'Otros egresos',
        };
    }

    /**
     * Secciones válidas para un tipo de cuenta.
     *
     * @return array<int, self>
     */
    public static function forType(AccountType $type): array
    {
        return match ($type) {
            AccountType::Income => [self::OperatingIncome, self::FinancialIncome, self::OtherIncome],
            AccountType::Expense => [self::Cogs, self::OperatingExpense, self::Depreciation, self::FinancialExpense, self::Tax, self::OtherExpense],
            default => [],
        };
    }

    /** Sección por defecto para cuentas legadas sin clasificar. */
    public static function defaultFor(AccountType $type): ?self
    {
        return match ($type) {
            AccountType::Income => self::OperatingIncome,
            AccountType::Expense => self::OperatingExpense,
            default => null,
        };
    }
}
