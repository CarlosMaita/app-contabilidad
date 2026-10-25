<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;
use App\Modules\Shared\Books\Book;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transferencia entre dos contabilidades (ej. aporte de la personal a la
 * familiar): un asiento en cada una, enlazados. Revertir uno revierte el
 * otro. No usa BelongsToBook porque abarca dos contabilidades.
 */
class BookTransfer extends Model
{
    protected $fillable = [
        'user_id',
        'from_book_id',
        'to_book_id',
        'from_entry_id',
        'to_entry_id',
        'amount',
        'date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromBook(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'from_book_id');
    }

    public function toBook(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'to_book_id');
    }

    public function fromEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'from_entry_id')->withoutGlobalScopes();
    }

    public function toEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'to_entry_id')->withoutGlobalScopes();
    }

    /** Transferencia a la que pertenece un asiento, si es parte de una. */
    public static function forEntry(JournalEntry $entry): ?self
    {
        return self::where('from_entry_id', $entry->id)->orWhere('to_entry_id', $entry->id)->first();
    }

    /** El otro asiento de la transferencia. */
    public function counterpartOf(JournalEntry $entry): JournalEntry
    {
        return $entry->id === $this->from_entry_id ? $this->toEntry : $this->fromEntry;
    }
}
