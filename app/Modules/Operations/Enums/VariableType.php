<?php

namespace App\Modules\Operations\Enums;

enum VariableType: string
{
    case Decimal = 'decimal';
    case Integer = 'integer';
    case String = 'string';
    case Date = 'date';
    case Boolean = 'boolean';
    case Account = 'account';

    public function label(): string
    {
        return match ($this) {
            self::Decimal => 'Monto (decimal)',
            self::Integer => 'Número entero',
            self::String => 'Texto',
            self::Date => 'Fecha',
            self::Boolean => 'Sí / No',
            self::Account => 'Cuenta contable',
        };
    }

    /**
     * Reglas de validación de Laravel para un valor de este tipo.
     *
     * @return array<int, string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::Decimal => ['numeric', 'decimal:0,2'],
            self::Integer => ['integer'],
            self::String => ['string', 'max:255'],
            self::Date => ['date'],
            self::Boolean => ['boolean'],
            self::Account => ['integer'],
        };
    }
}
