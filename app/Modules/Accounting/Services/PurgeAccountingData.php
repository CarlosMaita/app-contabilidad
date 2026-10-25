<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Models\MappingLine;
use App\Modules\Shared\Books\Book;

/**
 * Borra todos los datos contables de un usuario en orden seguro respecto
 * de las claves foráneas (los asientos referencian ejecuciones y mapeos
 * con restricción). Se usa al eliminar la cuenta de usuario: la
 * inmutabilidad contable protege los datos entre sí, no del derecho del
 * usuario a borrar todo lo suyo.
 */
class PurgeAccountingData
{
    public static function forBook(Book $book): void
    {
        $entries = JournalEntry::withoutGlobalScopes()->where('book_id', $book->id);

        JournalLine::whereIn('journal_entry_id', (clone $entries)->select('id'))->delete();
        (clone $entries)->update(['reverses_entry_id' => null]);
        (clone $entries)->delete();

        $mappings = AccountingMapping::withoutGlobalScopes()->where('book_id', $book->id);
        MappingLine::whereIn('accounting_mapping_id', (clone $mappings)->select('id'))->delete();
        (clone $mappings)->delete();

        $accounts = Account::withoutGlobalScopes()->where('book_id', $book->id);
        (clone $accounts)->update(['parent_id' => null]);
        (clone $accounts)->delete();
    }
}
