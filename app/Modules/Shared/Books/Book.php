<?php

namespace App\Modules\Shared\Books;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una contabilidad: plan de cuentas, asientos, operaciones y reportes
 * propios, gestionada por uno o más usuarios (miembros). Cada usuario tiene
 * su contabilidad personal y puede crear o sumarse a otras compartidas.
 */
class Book extends Model
{
    use HasFactory;

    public const PERSONAL = 'personal';

    public const SHARED = 'shared';

    public const OWNER = 'owner';

    public const MEMBER = 'member';

    protected $fillable = [
        'name',
        'kind',
        'template',
        'owner_id',
        'accounting_closed_until',
    ];

    protected function casts(): array
    {
        return [
            'accounting_closed_until' => 'date',
        ];
    }

    protected static function newFactory(): BookFactory
    {
        return BookFactory::new();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'contribution_account_id'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(BookInvitation::class);
    }

    public function isPersonal(): bool
    {
        return $this->kind === self::PERSONAL;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /** Nombre para mostrar: la personal se llama «Personal» para cada uno. */
    public function label(): string
    {
        return $this->isPersonal() ? 'Contabilidad personal' : $this->name;
    }
}
