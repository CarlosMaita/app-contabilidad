<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Services\ReportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('estado_de_resultados')]
#[Description('P&L multi-step de un rango: secciones por cuenta y líneas Utilidad bruta → EBITDA → EBIT → resultado financiero y otros → EBT → resultado neto, con márgenes (%) sobre los ingresos operativos. Por defecto, desde el 1 de enero hasta hoy.')]
#[IsReadOnly]
class EstadoDeResultados extends AccountingTool
{
    public function handle(Request $request, ReportService $reports): ResponseFactory
    {
        $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'incluir_cero' => ['nullable', 'boolean'],
        ]);

        $report = $reports->profitAndLoss(
            auth()->id(),
            $request->get('desde') ?? now()->startOfYear()->toDateString(),
            $request->get('hasta') ?? now()->toDateString(),
            (bool) $request->get('incluir_cero', false),
        );
        unset($report['cascade']);

        return $this->respond($report);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'desde' => $schema->string()->description('Fecha inicial AAAA-MM-DD (por defecto 1 de enero del año en curso).'),
            'hasta' => $schema->string()->description('Fecha final AAAA-MM-DD (por defecto hoy).'),
            'incluir_cero' => $schema->boolean()->description('Incluir cuentas sin movimientos (por defecto no).'),
        ];
    }
}
