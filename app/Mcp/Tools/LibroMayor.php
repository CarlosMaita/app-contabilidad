<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Services\LedgerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('libro_mayor')]
#[Description('Movimientos de una cuenta en un rango con saldo inicial, saldo acumulado y saldo final (según la naturaleza de la cuenta). Una cuenta principal con auxiliares muestra el consolidado.')]
#[IsReadOnly]
class LibroMayor extends AccountingTool
{
    public function handle(Request $request, LedgerService $ledger): ResponseFactory
    {
        $request->validate([
            'cuenta' => ['required'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $account = $this->findAccount($request->get('cuenta'));
        $result = $ledger->movements($account, $request->get('desde'), $request->get('hasta'));

        return $this->respond([
            'cuenta' => $account,
            'naturaleza' => $account->type->isDebitNature() ? 'deudora' : 'acreedora',
            'desde' => $request->get('desde'),
            'hasta' => $request->get('hasta'),
            'saldo_inicial' => $result['opening'],
            'saldo_final' => $result['closing'],
            'movimientos' => $result['movements'],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'cuenta' => $schema->string()->description('Código de la cuenta (p. ej. «1.1.02») o su id.')->required(),
            'desde' => $schema->string()->description('Fecha inicial AAAA-MM-DD (opcional).'),
            'hasta' => $schema->string()->description('Fecha final AAAA-MM-DD (opcional).'),
        ];
    }
}
