<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\PnlSection;
use App\Modules\Shared\Traits\BelongsToUser;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use BelongsToUser;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'name',
        'type',
        'pnl_section',
        'parent_id',
        'is_postable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'pnl_section' => PnlSection::class,
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Sección del P&L efectiva: la guardada si es válida para el tipo,
     * o la sección por defecto (cuentas legadas sin clasificar).
     */
    public function effectivePnlSection(): ?PnlSection
    {
        if ($this->pnl_section !== null && in_array($this->pnl_section, PnlSection::forType($this->type), true)) {
            return $this->pnl_section;
        }

        return PnlSection::defaultFor($this->type);
    }

    protected static function newFactory()
    {
        return AccountFactory::new();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_postable', true)->where('is_active', true);
    }
}
