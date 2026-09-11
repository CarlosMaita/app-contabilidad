<?php

namespace App\Modules\Shared\Money;

/**
 * Helpers de montos como strings decimales de 2 posiciones (bcmath).
 * Nunca floats en columnas contables.
 */
final class Money
{
    public static function normalize(float|int|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, 2);
    }

    public static function equals(string $a, string $b): bool
    {
        return bccomp($a, $b, 2) === 0;
    }

    public static function isZero(string $value): bool
    {
        return bccomp($value, '0', 2) === 0;
    }

    public static function isNegative(string $value): bool
    {
        return bccomp($value, '0', 2) === -1;
    }
}
