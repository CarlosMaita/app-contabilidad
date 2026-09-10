<?php

namespace App\Modules\Operations\Models;

use App\Modules\Shared\Traits\BelongsToUser;
use Database\Factories\OperationTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationType extends Model
{
    use BelongsToUser;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): OperationTypeFactory
    {
        return OperationTypeFactory::new();
    }

    public function variables(): HasMany
    {
        return $this->hasMany(OperationVariable::class)->orderBy('sort_order');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(OperationExecution::class);
    }
}
