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

    /** Clases Tailwind para el badge de estado (borde 1px, sin píldoras). */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'border-neutral-400 text-neutral-700',
            self::Posted => 'border-ink text-ink',
            self::Failed => 'border-accent text-accent-600',
            self::Unmapped => 'border-neutral-400 text-neutral-700',
            self::Voided => 'border-neutral-400 text-neutral-500 line-through',
        };
    }
}
