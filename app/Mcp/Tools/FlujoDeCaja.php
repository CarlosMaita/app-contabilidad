<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Services\ReportService;
use App\Modules\Shared\Books\CurrentBook;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('flujo_de_caja')]
#[Description('Estado de flujo de efectivo (método indirecto) de un rango: resultado neto, ajustes sin efectivo, capital de trabajo, inversión y financiamiento; variación neta conciliada con el saldo real de caja y bancos. Por defecto, desde el 1 de enero hasta hoy.')]
#[IsReadOnly]
class FlujoDeCaja extends AccountingTool
{
    public function handle(Request $request, ReportService $reports): ResponseFactory
    {
        $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'incluir_cero' => ['nullable', 'boolean'],
        ]);

        return $this->respond($reports->cashFlow(
            CurrentBook::id(),
            $request->get('desde') ?? now()->startOfYear()->toDateString(),
            $request->get('hasta') ?? now()->toDateString(),
            (bool) $request->get('incluir_cero', false),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'desde' => $schema->string()->description('Fecha inicial AAAA-MM-DD (por defecto 1 de enero del año en curso).'),
            'hasta' => $schema->string()->description('Fecha final AAAA-MM-DD (por defecto hoy).'),
            'incluir_cero' => $schema->boolean()->description('Incluir cuentas sin variación (por defecto no).'),
        ];
    }
}
