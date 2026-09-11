<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Shared\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
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

    public function execution(): BelongsTo
    {
        return $this->belongsTo(OperationExecution::class, 'operation_execution_id');
    }

    /**
     * Filtros del libro diario: rango de fechas y búsqueda por número,
     * descripción, cuenta u operación de origen.
     */
    public function scopeFiltered(Builder $query, string $search = '', string $from = '', string $to = ''): Builder
    {
        return $query
            ->when($from !== '', fn (Builder $q) => $q->whereDate('date', '>=', $from))
            ->when($to !== '', fn (Builder $q) => $q->whereDate('date', '<=', $to))
            ->when(trim($search) !== '', function (Builder $q) use ($search): void {
                $term = trim($search);
                $like = '%'.$term.'%';

                $q->where(function (Builder $q) use ($term, $like): void {
                    $q->where('description', 'like', $like)
                        ->orWhereHas('lines.account', fn (Builder $q) => $q
                            ->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like))
                        ->orWhereHas('execution.operationType', fn (Builder $q) => $q->where('name', 'like', $like));

                    if (ctype_digit($term)) {
                        $q->orWhere('number', (int) $term);
                    }
                });
            });
    }
}
