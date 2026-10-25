<?php

namespace App\Modules\Shared\Traits;

use App\Modules\Shared\Books\Book;
use App\Modules\Shared\Books\CurrentBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Datos de negocio de una contabilidad: global scope por la contabilidad
 * actual del usuario autenticado y autocompletado de book_id (y de
 * created_by en los modelos que registran autor) al crear.
 */
trait BelongsToBook
{
    protected static function bootBelongsToBook(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->book_id) && ($bookId = CurrentBook::id()) !== null) {
                $model->book_id = $bookId;
            }

            // isFillable() da true con el modelo sin protección (factories, seeders).
            if (in_array('created_by', $model->getFillable(), true) && empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        static::addGlobalScope('belongsToBook', function (Builder $builder): void {
            if (($bookId = CurrentBook::id()) !== null) {
                $builder->where($builder->getModel()->getTable().'.book_id', $bookId);
            }
        });
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
