<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\EntrySide;
use Database\Factories\MappingLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MappingLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'accounting_mapping_id',
        'side',
        'account_id',
        'account_variable',
        'amount_expression',
        'memo_template',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'side' => EntrySide::class,
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): MappingLineFactory
    {
        return MappingLineFactory::new();
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(AccountingMapping::class, 'accounting_mapping_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
