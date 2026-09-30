<?php

namespace App\Modules\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CashFlowExport implements FromArray, WithHeadings
{
    /**
     * @param  array{net_income: string, sections: array<string, array{rows: array, total: string}>, totals: array<string, string>, net_change: string, cash_opening: string, cash_closing: string}  $report
     */
    public function __construct(
        private readonly array $report,
    ) {}

    public function headings(): array
    {
        return ['Código', 'Concepto', 'Importe'];
    }

    public function array(): array
    {
        $r = $this->report;
        $rows = [['', 'ACTIVIDADES OPERATIVAS', '']];
        $rows[] = ['', 'Resultado neto del período', $r['net_income']];

        $accountRows = function (string $key, ?string $subtitle = null) use (&$rows, $r): void {
            if ($subtitle !== null && $r['sections'][$key]['rows'] !== []) {
                $rows[] = ['', $subtitle, ''];
            }

            foreach ($r['sections'][$key]['rows'] as $row) {
                $rows[] = [$row['account']->code, '    '.$row['account']->name, $row['amount']];
            }
        };

        $accountRows('non_cash', 'Ajustes sin movimiento de efectivo');
        $accountRows('operating', 'Variaciones en capital de trabajo');
        $rows[] = ['', 'FLUJO NETO DE ACTIVIDADES OPERATIVAS', $r['totals']['operating']];

        $rows[] = ['', 'ACTIVIDADES DE INVERSIÓN', ''];
        $accountRows('investing');
        $rows[] = ['', 'FLUJO NETO DE ACTIVIDADES DE INVERSIÓN', $r['totals']['investing']];

        $rows[] = ['', 'ACTIVIDADES DE FINANCIAMIENTO', ''];
        $accountRows('financing');
        $rows[] = ['', 'FLUJO NETO DE ACTIVIDADES DE FINANCIAMIENTO', $r['totals']['financing']];

        $rows[] = ['', 'VARIACIÓN NETA DEL EFECTIVO', $r['net_change']];
        $rows[] = ['', 'Efectivo al inicio del período', $r['cash_opening']];
        $rows[] = ['', 'EFECTIVO AL FINAL DEL PERÍODO', $r['cash_closing']];

        return $rows;
    }
}
