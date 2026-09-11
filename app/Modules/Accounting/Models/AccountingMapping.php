<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Shared\Traits\BelongsToUser;
use Database\Factories\AccountingMappingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingMapping extends Model
{
    use BelongsToUser;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'operation_type_id',
        'version',
        'is_active',
        'description_template',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): AccountingMappingFactory
    {
        return AccountingMappingFactory::new();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MappingLine::class)->orderBy('sort_order');
    }
}
