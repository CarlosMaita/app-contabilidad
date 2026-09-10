<?php

namespace App\Modules\Operations\Models;

use App\Modules\Operations\Enums\VariableType;
use Database\Factories\OperationVariableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationVariable extends Model
{
    use HasFactory;

    protected $fillable = [
        'operation_type_id',
        'name',
        'label',
        'type',
        'is_required',
        'default_value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => VariableType::class,
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): OperationVariableFactory
    {
        return OperationVariableFactory::new();
    }

    public function operationType(): BelongsTo
    {
        return $this->belongsTo(OperationType::class);
    }
}
