<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Services\ReportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('balance_general')]
#[Description('Estado de situación patrimonial a una fecha: activo, pasivo y patrimonio por cuenta (level = profundidad en el árbol), resultado acumulado y control Activo = Pasivo + Patrimonio + Resultado. Los auxiliares se consolidan en su principal.')]
#[IsReadOnly]
class BalanceGeneral extends AccountingTool
{
    public function handle(Request $request, ReportService $reports): ResponseFactory
    {
        $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'incluir_cero' => ['nullable', 'boolean'],
        ]);

        return $this->respond($reports->balanceSheet(
            auth()->id(),
            $request->get('fecha') ?? now()->toDateString(),
            (bool) $request->get('incluir_cero', false),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fecha' => $schema->string()->description('Fecha de corte AAAA-MM-DD (por defecto hoy).'),
            'incluir_cero' => $schema->boolean()->description('Incluir cuentas con saldo cero (por defecto no).'),
        ];
    }
}
