<?php

namespace App\Modules\Operations\Models;

use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Shared\Traits\BelongsToBook;
use Database\Factories\OperationExecutionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationExecution extends Model
{
    use BelongsToBook;
    use HasFactory;

    protected $fillable = [
        'book_id',
        'created_by',
        'operation_type_id',
        'payload',
        'executed_at',
        'description',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'executed_at' => 'date',
            'status' => ExecutionStatus::class,
        ];
    }

    protected static function newFactory(): OperationExecutionFactory
    {
        return OperationExecutionFactory::new();
    }

    public function operationType(): BelongsTo
    {
        return $this->belongsTo(OperationType::class);
    }
}
