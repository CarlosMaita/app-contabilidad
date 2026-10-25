<?php

namespace App\Books;

use App\Models\User;
use App\Modules\Shared\Books\Book;
use Illuminate\Support\Facades\Auth;

/**
 * Ejecuta código como si el usuario trabajara en otra contabilidad: los
 * servicios usan la contabilidad actual del usuario autenticado
 * (CurrentBook), así que se autentica temporalmente un clon con ese libro.
 */
class InBook
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(Book $book, User $user, callable $callback): mixed
    {
        $previous = Auth::user();
        Auth::setUser((clone $user)->forceFill(['current_book_id' => $book->id]));

        try {
            return $callback();
        } finally {
            $previous !== null ? Auth::setUser($previous) : Auth::forgetUser();
        }
    }
}
