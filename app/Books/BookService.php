<?php

namespace App\Books;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\AccountManager;
use App\Modules\Accounting\Services\DefaultChartOfAccounts;
use App\Modules\Accounting\Services\PurgeAccountingData;
use App\Modules\Operations\Services\PurgeOperationsData;
use App\Modules\Shared\Books\Book;
use App\Modules\Shared\Books\BookInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de vida de las contabilidades: alta con plantilla, miembros e
 * invitaciones, aportes por miembro y baja. Coordina los módulos Accounting
 * y Operations (por eso vive fuera de ellos, como App\Demo).
 */
class BookService
{
    public const TEMPLATES = [
        'business' => 'Negocio',
        'household' => 'Hogar',
    ];

    /** Crea una contabilidad compartida con su plan según la plantilla. */
    public function create(User $owner, string $name, string $template): Book
    {
        if (! array_key_exists($template, self::TEMPLATES)) {
            throw ValidationException::withMessages(['template' => 'Plantilla inválida.']);
        }

        return DB::transaction(function () use ($owner, $name, $template): Book {
            $book = Book::create([
                'name' => $name,
                'kind' => Book::SHARED,
                'template' => $template,
                'owner_id' => $owner->id,
            ]);
            $book->members()->attach($owner->id, ['role' => Book::OWNER]);

            match ($template) {
                'household' => HouseholdTemplate::seed($book),
                default => DefaultChartOfAccounts::seedFor($book),
            };

            $this->ensureContributionAccount($book, $owner);

            return $book;
        });
    }

    /**
     * Invita por correo: si ya hay un usuario con ese correo se suma al
     * instante; si no, queda pendiente hasta que se registre.
     *
     * @return User|null el usuario agregado, o null si quedó pendiente
     */
    public function invite(Book $book, User $inviter, string $email): ?User
    {
        $this->assertOwner($book, $inviter);

        if ($book->isPersonal()) {
            throw ValidationException::withMessages(['email' => 'La contabilidad personal no se comparte. Creá una contabilidad compartida.']);
        }

        $email = mb_strtolower(trim($email));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null) {
            BookInvitation::firstOrCreate(['book_id' => $book->id, 'email' => $email], ['invited_by' => $inviter->id]);

            return null;
        }

        if ($user->is_demo) {
            throw ValidationException::withMessages(['email' => 'No se puede invitar a una cuenta demo.']);
        }

        if ($book->hasMember($user)) {
            throw ValidationException::withMessages(['email' => "{$user->name} ya es miembro de esta contabilidad."]);
        }

        $this->addMember($book, $user);

        return $user;
    }

    public function addMember(Book $book, User $user): void
    {
        DB::transaction(function () use ($book, $user): void {
            $book->members()->syncWithoutDetaching([$user->id => ['role' => Book::MEMBER]]);
            $this->ensureContributionAccount($book, $user);
        });
    }

    /** Al registrarse, el usuario se suma a las contabilidades que lo invitaron. */
    public function acceptInvitations(User $user): int
    {
        $invitations = BookInvitation::whereRaw('lower(email) = ?', [mb_strtolower($user->email)])->with('book')->get();

        foreach ($invitations as $invitation) {
            if ($invitation->book !== null) {
                $this->addMember($invitation->book, $user);
            }
            $invitation->delete();
        }

        return $invitations->count();
    }

    public function removeMember(Book $book, User $actor, User $member): void
    {
        $this->assertOwner($book, $actor);

        if ($member->id === $book->owner_id) {
            throw ValidationException::withMessages(['member' => 'El dueño no puede quitarse; borrá la contabilidad o pasale la propiedad a otro miembro.']);
        }

        $book->members()->detach($member->id);
        $this->leaveCurrent($member, $book);
    }

    public function leave(Book $book, User $member): void
    {
        if ($book->isPersonal() || $member->id === $book->owner_id) {
            throw ValidationException::withMessages(['book' => 'El dueño no puede salir de su contabilidad.']);
        }

        $book->members()->detach($member->id);
        $this->leaveCurrent($member, $book);
    }

    public function rename(Book $book, User $actor, string $name): void
    {
        $this->assertOwner($book, $actor);
        $book->update(['name' => $name]);
    }

    /** Borra una contabilidad compartida con todos sus datos. */
    public function delete(Book $book, User $actor): void
    {
        $this->assertOwner($book, $actor);

        if ($book->isPersonal()) {
            throw ValidationException::withMessages(['book' => 'La contabilidad personal no se puede borrar.']);
        }

        $this->purge($book);
    }

    /**
     * Antes de borrar un usuario: se van su personal y las compartidas donde
     * es el único miembro; en las demás, la propiedad pasa al miembro más
     * antiguo.
     */
    public function handleUserDeletion(User $user): void
    {
        foreach ($user->books()->get() as $book) {
            $others = $book->members()->where('users.id', '!=', $user->id)->orderBy('book_user.created_at')->get();

            if ($book->isPersonal() || $others->isEmpty()) {
                $this->purge($book);
            } elseif ($book->owner_id === $user->id) {
                $heir = $others->first();
                $book->update(['owner_id' => $heir->id]);
                $book->members()->updateExistingPivot($heir->id, ['role' => Book::OWNER]);
            }
        }
    }

    /**
     * Auxiliar «Aportes de {persona}» del miembro (bajo «Aportes de los
     * miembros» en Hogar o «Capital» en Negocio). Idempotente.
     */
    public function ensureContributionAccount(Book $book, User $user): ?Account
    {
        $pivot = DB::table('book_user')->where('book_id', $book->id)->where('user_id', $user->id)->first();

        if ($pivot?->contribution_account_id !== null) {
            $existing = Account::withoutGlobalScopes()->where('book_id', $book->id)->find($pivot->contribution_account_id);
            if ($existing !== null) {
                return $existing;
            }
        }

        $parentCode = $book->template === 'household' ? HouseholdTemplate::CONTRIBUTIONS : '3.1';
        $parent = Account::withoutGlobalScopes()->where('book_id', $book->id)->where('code', $parentCode)->first();

        if ($parent === null || $parent->is_auxiliary) {
            return null;
        }

        $siblings = Account::withoutGlobalScopes()->where('book_id', $book->id)->where('parent_id', $parent->id)->pluck('code');
        $next = 1;
        while ($siblings->contains(sprintf('%s.%02d', $parentCode, $next))) {
            $next++;
        }

        $account = $this->asBook($book, $user, fn () => app(AccountManager::class)->save([
            'code' => sprintf('%s.%02d', $parentCode, $next),
            'name' => "Aportes de {$user->name}",
            'type' => $parent->type->value,
            'parent_id' => $parent->id,
            'is_postable' => true,
            'is_auxiliary' => true,
            'is_active' => true,
        ]));

        DB::table('book_user')->where('book_id', $book->id)->where('user_id', $user->id)
            ->update(['contribution_account_id' => $account->id]);

        return $account;
    }

    private function purge(Book $book): void
    {
        DB::transaction(function () use ($book): void {
            PurgeAccountingData::forBook($book);
            PurgeOperationsData::forBook($book);
            DB::table('users')->where('current_book_id', $book->id)->update(['current_book_id' => null]);
            $book->delete();
        });
    }

    private function assertOwner(Book $book, User $actor): void
    {
        abort_unless($book->owner_id === $actor->id, 403, 'Solo el dueño puede administrar esta contabilidad.');
    }

    private function leaveCurrent(User $member, Book $book): void
    {
        if ($member->current_book_id === $book->id) {
            $member->forceFill(['current_book_id' => $member->personalBook()->id])->save();
        }
    }

    /**
     * Ejecuta como si el usuario trabajara en esa contabilidad (los servicios
     * usan la contabilidad actual del usuario autenticado).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function asBook(Book $book, User $user, callable $callback): mixed
    {
        $previousUser = Auth::user();
        $proxy = (clone $user)->forceFill(['current_book_id' => $book->id]);
        Auth::setUser($proxy);

        try {
            return $callback();
        } finally {
            $previousUser !== null ? Auth::setUser($previousUser) : Auth::forgetUser();
        }
    }
}
