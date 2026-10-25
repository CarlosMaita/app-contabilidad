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

#[Name('saldos_de_cuentas')]
#[Description('Saldo de cada cuenta con movimientos a una fecha (inclusive), agrupado por tipo. El saldo se expresa según la naturaleza de la cuenta (side: debit = deudor, credit = acreedor). Incluye el control sumas deudoras = acreedoras. Las cuentas en cero se omiten.')]
#[IsReadOnly]
class SaldosDeCuentas extends AccountingTool
{
    public function handle(Request $request, ReportService $reports): ResponseFactory
    {
        $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->respond($reports->accountBalances(
            CurrentBook::id(),
            $request->get('fecha') ?? now()->toDateString(),
            (string) $request->get('buscar', ''),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fecha' => $schema->string()->description('Fecha de corte AAAA-MM-DD (por defecto hoy).'),
            'buscar' => $schema->string()->description('Filtra por código o nombre (opcional).'),
        ];
    }
}
