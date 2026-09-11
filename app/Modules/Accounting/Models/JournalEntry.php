<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Shared\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'number',
        'date',
        'description',
        'operation_execution_id',
        'accounting_mapping_id',
        'reverses_entry_id',
        'status',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'date' => 'date',
            'status' => JournalEntryStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(AccountingMapping::class, 'accounting_mapping_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }
}
