<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Shared\Books\Book;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        // Todo usuario tiene su contabilidad personal desde que existe.
        static::created(fn (User $user) => $user->personalBook());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_demo' => 'boolean',
        ];
    }

    /** Contabilidades de las que es miembro (incluida la personal). */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)
            ->withPivot(['role', 'contribution_account_id'])
            ->withTimestamps();
    }

    /** Su contabilidad personal; se crea si todavía no existe. */
    public function personalBook(): Book
    {
        $book = Book::where('owner_id', $this->id)->where('kind', Book::PERSONAL)->first();

        if ($book === null) {
            $book = Book::create(['name' => 'Personal', 'kind' => Book::PERSONAL, 'template' => 'business', 'owner_id' => $this->id]);
            $book->members()->attach($this->id, ['role' => Book::OWNER]);

            if ($this->current_book_id === null) {
                $this->forceFill(['current_book_id' => $book->id])->saveQuietly();
            }
        }

        return $book;
    }

    /**
     * Tokens personales del MCP (tabla de Sanctum, ver App\Mcp\PersonalTokens).
     * Los tokens OAuth de Passport son tokens().
     */
    public function mcpTokens(): MorphMany
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }
}
