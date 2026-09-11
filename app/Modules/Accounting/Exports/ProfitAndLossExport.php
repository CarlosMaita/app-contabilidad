<?php

namespace App\Modules\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProfitAndLossExport implements FromArray, WithHeadings
{
    /**
     * @param  array{from: ?string, to: string, income: array{rows: array, total: string}, expense: array{rows: array, total: string}, result: string}  $report
     */
    public function __construct(
        private readonly array $report,
    ) {}

    public function headings(): array
    {
        return ['Sección', 'Código', 'Cuenta', 'Monto'];
    }

    public function array(): array
    {
        $rows = [];

        foreach (['income' => 'Ingresos', 'expense' => 'Gastos'] as $key => $label) {
            foreach ($this->report[$key]['rows'] as $row) {
                $rows[] = [$label, $row['account']->code, $row['account']->name, $row['amount']];
            }
            $rows[] = [$label, '', 'TOTAL '.mb_strtoupper($label), $this->report[$key]['total']];
        }

        $rows[] = ['', '', 'RESULTADO NETO', $this->report['result']];

        return $rows;
    }
}
