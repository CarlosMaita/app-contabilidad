<?php

namespace App\Modules\Shared\Books;

use App\Models\User;
use ArrayObject;
use Illuminate\Support\Facades\Auth;

/**
 * Contabilidad en la que trabaja el usuario autenticado: la que eligió
 * (users.current_book_id) si sigue siendo miembro, o si no su personal.
 * Todo el alcance de datos (BelongsToBook) se resuelve a través de acá.
 * La resolución se cachea en el contenedor (vive lo que dura la request).
 */
class CurrentBook
{
    public static function id(): ?int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $cache = self::cache();
        $key = $user->id.':'.($user->current_book_id ?? 0);

        return $cache[$key] ??= self::resolve($user);
    }

    public static function get(): ?Book
    {
        $id = self::id();

        return $id !== null ? Book::find($id) : null;
    }

    /** Cambia la contabilidad actual del usuario (debe ser miembro). */
    public static function switchTo(User $user, Book $book): void
    {
        abort_unless($book->hasMember($user), 403);

        $user->forceFill(['current_book_id' => $book->id])->save();
        self::cache()[$user->id.':'.$book->id] = $book->id;
    }

    public static function flush(): void
    {
        self::cache()->exchangeArray([]);
    }

    private static function resolve(User $user): int
    {
        if ($user->current_book_id !== null
            && Book::query()->whereKey($user->current_book_id)->whereHas('members', fn ($q) => $q->whereKey($user->id))->exists()) {
            return $user->current_book_id;
        }

        return $user->personalBook()->id;
    }

    private static function cache(): ArrayObject
    {
        $app = app();

        if (! $app->bound(self::class.'.cache')) {
            $app->instance(self::class.'.cache', new ArrayObject);
        }

        return $app->make(self::class.'.cache');
    }
}
