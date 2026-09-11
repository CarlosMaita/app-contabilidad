<?php

namespace App\Modules\Accounting\Enums;

enum EntrySide: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Debit => 'Debe',
            self::Credit => 'Haber',
        };
    }
}
