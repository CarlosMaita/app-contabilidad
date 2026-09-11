<?php

namespace App\Modules\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProfitAndLossExport implements FromArray, WithHeadings
{
    /**
     * @param  array{sections: array<string, array{label: string, rows: array, total: string}>, lines: array<string, string>, margins: array<string, ?string>}  $report
     */
    public function __construct(
        private readonly array $report,
    ) {}

    public function headings(): array
    {
        return ['Código', 'Cuenta / Línea', 'Importe', 'Margen %'];
    }

    public function array(): array
    {
        $rows = [];

        $section = function (string $key, string $sign) use (&$rows): void {
            $s = $this->report['sections'][$key];

            if ($s['rows'] === [] && (float) $s['total'] == 0.0) {
                return;
            }

            $rows[] = ['', ($sign === '−' ? '(−) ' : '').mb_strtoupper($s['label']), $s['total'], ''];

            foreach ($s['rows'] as $row) {
                $rows[] = [
                    $row['account']->code,
                    str_repeat('    ', $row['level'] + 1).$row['account']->name,
                    $row['amount'],
                    '',
                ];
            }
        };

        $line = function (string $key, string $label, ?string $marginKey = null) use (&$rows): void {
            $rows[] = [
                '',
                $label,
                $this->report['lines'][$key],
                $marginKey !== null ? ($this->report['margins'][$marginKey] ?? '') : '',
            ];
        };

        $section('operating_income', '+');
        $section('cogs', '−');
        $line('gross', 'UTILIDAD BRUTA', 'gross');
        $section('operating_expense', '−');
        $line('ebitda', 'EBITDA', 'ebitda');
        $section('depreciation', '−');
        $line('ebit', 'EBIT · UTILIDAD OPERATIVA', 'ebit');
        $section('financial_income', '+');
        $section('financial_expense', '−');
        $line('financial', 'Resultado financiero');
        $section('other_income', '+');
        $section('other_expense', '−');
        $line('other', 'Resultado no operativo');
        $line('ebt', 'RESULTADO ANTES DE IMPUESTOS');
        $section('tax', '−');
        $line('net', 'RESULTADO NETO', 'net');

        return $rows;
    }
}
