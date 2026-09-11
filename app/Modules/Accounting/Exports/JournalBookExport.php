<?php

namespace App\Modules\Accounting\Exports;

use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class JournalBookExport implements FromArray, WithHeadings
{
    /**
     * @param  Collection<int, JournalEntry>  $entries
     */
    public function __construct(
        private readonly Collection $entries,
    ) {}

    public function headings(): array
    {
        return ['N°', 'Fecha', 'Descripción', 'Origen', 'Estado', 'Cuenta', 'Memo', 'Debe', 'Haber'];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->entries as $entry) {
            foreach ($entry->lines as $line) {
                $rows[] = [
                    $entry->number,
                    $entry->date->format('d/m/Y'),
                    $entry->description,
                    $entry->execution?->operationType?->name ?? 'Manual',
                    $entry->status->label(),
                    $line->account->code.' '.$line->account->name,
                    $line->memo,
                    (string) $line->debit,
                    (string) $line->credit,
                ];
            }
        }

        return $rows;
    }
}
