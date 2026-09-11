<?php

namespace App\Modules\Accounting\Exports;

use App\Modules\Accounting\Models\Account;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BalanceSheetExport implements FromArray, WithHeadings
{
    /**
     * @param  array{as_of: string, sections: array<string, array{label: string, rows: array<int, array{account: Account, level: int, amount: string}>, total: string}>, result: string, check: array{assets: string, liabilities_equity: string, balanced: bool}}  $report
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

        foreach ($this->report['sections'] as $key => $section) {
            foreach ($section['rows'] as $row) {
                $rows[] = [$section['label'], $row['account']->code, $row['account']->name, $row['amount']];
            }

            if ($key === 'equity') {
                $rows[] = [$section['label'], '', 'Resultado del período', $this->report['result']];
            }

            $rows[] = [$section['label'], '', 'TOTAL '.mb_strtoupper($section['label']), $section['total']];
        }

        $rows[] = ['', '', 'Activo vs Pasivo + Patrimonio + Resultado', $this->report['check']['assets'].' / '.$this->report['check']['liabilities_equity']];

        return $rows;
    }
}
