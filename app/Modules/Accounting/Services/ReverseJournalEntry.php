<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Shared\Money\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Inmutabilidad contable: un asiento nunca se edita ni se borra;
 * se corrige con un contra-asiento que invierte Debe y Haber.
 */
class ReverseJournalEntry
{
    public function __construct(
        private readonly JournalEntryBuilder $builder,
    ) {}

    public function reverse(JournalEntry $entry, ?string $date = null): JournalEntry
    {
        if ($entry->status === JournalEntryStatus::Reversed) {
            throw new RuntimeException("El asiento #{$entry->number} ya está revertido.");
        }

        $lines = $entry->lines->map(fn (JournalLine $line): array => [
            'side' => Money::isZero((string) $line->debit) ? EntrySide::Debit : EntrySide::Credit,
            'account_id' => $line->account_id,
            'amount' => Money::isZero((string) $line->debit) ? (string) $line->credit : (string) $line->debit,
            'memo' => $line->memo,
        ])->all();

        return DB::transaction(function () use ($entry, $lines, $date): JournalEntry {
            $reversal = $this->builder->post(
                userId: $entry->user_id,
                date: $date ?? $entry->date->toDateString(),
                description: "Reversión del asiento #{$entry->number}: {$entry->description}",
                lines: $lines,
                mappingId: $entry->accounting_mapping_id,
                reversesEntryId: $entry->id,
            );

            $entry->update(['status' => JournalEntryStatus::Reversed]);

            return $reversal;
        });
    }
}
