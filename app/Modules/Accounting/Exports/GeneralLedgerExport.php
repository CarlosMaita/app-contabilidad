<?php

namespace App\Modules\Accounting\Exports;

use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class GeneralLedgerExport implements FromArray, WithHeadings
{
    /**
     * @param  array{opening: string, closing: string, movements: Collection<int, object>}  $result
     */
    public function __construct(
        private readonly Account $account,
        private readonly array $result,
    ) {}

    public function headings(): array
    {
        return ['Fecha', 'Asiento', 'Descripción', 'Memo', 'Debe', 'Haber', 'Saldo'];
    }

    public function array(): array
    {
        $rows = [['', '', 'Saldo inicial', '', '', '', $this->result['opening']]];

        foreach ($this->result['movements'] as $m) {
            $rows[] = [
                $m->date->format('d/m/Y'),
                $m->number,
                $m->description,
                $m->memo,
                $m->debit,
                $m->credit,
                $m->balance,
            ];
        }

        $rows[] = ['', '', 'Saldo final', '', '', '', $this->result['closing']];

        return $rows;
    }
}
