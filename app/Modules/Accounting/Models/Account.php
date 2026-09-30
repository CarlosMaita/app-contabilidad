<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\CashFlowSection;
use App\Modules\Accounting\Enums\PnlSection;
use App\Modules\Shared\Traits\BelongsToUser;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        'cash_flow_section',
        'parent_id',
        'is_postable',
        'is_auxiliary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'pnl_section' => PnlSection::class,
            'cash_flow_section' => CashFlowSection::class,
            'is_postable' => 'boolean',
            'is_auxiliary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Actividad del flujo de efectivo efectiva: la guardada si es válida,
     * si no la del padre (auxiliares y sub-cuentas heredan), y si no la
     * de defecto del tipo. $byId evita consultas al recorrer muchas cuentas.
     */
    public function effectiveCashFlowSection(?Collection $byId = null): ?CashFlowSection
    {
        $valid = CashFlowSection::forType($this->type);

        if ($valid === []) {
            return null;
        }

        if ($this->cash_flow_section !== null && in_array($this->cash_flow_section, $valid, true)) {
            return $this->cash_flow_section;
        }

        if ($this->parent_id !== null) {
            $parent = $byId?->get($this->parent_id) ?? $this->parent;

            if ($parent !== null && $parent->type === $this->type) {
                return $parent->effectiveCashFlowSection($byId);
            }
        }

        return CashFlowSection::defaultFor($this->type);
    }

    public function auxiliaries(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_auxiliary', true)
            ->orderBy('code');
    }

    public function hasAuxiliaries(): bool
    {
        return $this->auxiliaries()->exists();
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
