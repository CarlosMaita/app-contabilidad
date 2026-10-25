<?php

namespace App\Modules\Operations\Services;

use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Operations\Models\OperationVariable;
use App\Modules\Shared\Books\Book;

/**
 * Borra todos los datos operativos de un usuario. Debe correr después de
 * la purga contable: los asientos referencian las ejecuciones.
 */
class PurgeOperationsData
{
    public static function forBook(Book $book): void
    {
        OperationExecution::withoutGlobalScopes()->where('book_id', $book->id)->delete();

        $types = OperationType::withoutGlobalScopes()->where('book_id', $book->id);
        OperationVariable::whereIn('operation_type_id', (clone $types)->select('id'))->delete();
        (clone $types)->delete();
    }
}
