<?php

namespace App\Modules\Operations\Enums;

enum ExecutionStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Failed = 'failed';
    case Unmapped = 'unmapped';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Posted => 'Contabilizado',
            self::Failed => 'Con error',
            self::Unmapped => 'Sin mapeo',
            self::Voided => 'Anulado',
        };
    }

    /** Clases Tailwind para el badge de estado. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Posted => 'bg-green-100 text-green-800',
            self::Failed => 'bg-red-100 text-red-800',
            self::Unmapped => 'bg-gray-100 text-gray-700',
            self::Voided => 'bg-gray-200 text-gray-500 line-through',
        };
    }
}
