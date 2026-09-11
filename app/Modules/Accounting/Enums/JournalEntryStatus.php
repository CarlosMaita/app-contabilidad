<?php

namespace App\Modules\Accounting\Enums;

enum JournalEntryStatus: string
{
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Posted => 'Registrado',
            self::Reversed => 'Revertido',
        };
    }
}
