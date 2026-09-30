<?php

namespace App\Modules\Accounting\Enums;

/**
 * Actividad del estado de flujo de efectivo (método indirecto) a la que
 * aporta la variación de una cuenta de balance:
 *
 *   Resultado neto
 *   + ajustes sin movimiento de efectivo (depreciación acumulada)
 *   ± variaciones de capital de trabajo              = Flujo operativo
 *   ± variaciones de activos de largo plazo          = Flujo de inversión
 *   ± variaciones de deuda financiera y patrimonio   = Flujo de financiamiento
 *
 * Las cuentas de efectivo no son una actividad: su variación es el
 * resultado que el estado explica.
 */
enum CashFlowSection: string
{
    case Cash = 'cash';
    case NonCash = 'non_cash';
    case Operating = 'operating';
    case Investing = 'investing';
    case Financing = 'financing';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo y equivalentes',
            self::NonCash => 'Ajuste sin movimiento de efectivo',
            self::Operating => 'Capital de trabajo (operativa)',
            self::Investing => 'Actividades de inversión',
            self::Financing => 'Actividades de financiamiento',
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
            AccountType::Asset => [self::Cash, self::Operating, self::NonCash, self::Investing, self::Financing],
            AccountType::Liability => [self::Operating, self::NonCash, self::Investing, self::Financing],
            AccountType::Equity => [self::Financing],
            default => [],
        };
    }

    /** Sección por defecto para cuentas sin clasificar ni padre clasificado. */
    public static function defaultFor(AccountType $type): ?self
    {
        return match ($type) {
            AccountType::Asset, AccountType::Liability => self::Operating,
            AccountType::Equity => self::Financing,
            default => null,
        };
    }
}
