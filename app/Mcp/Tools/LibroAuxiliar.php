<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\LedgerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('libro_auxiliar')]
#[Description('Libros auxiliares (p. ej. cuentas por cobrar por cliente). Sin «cuenta_principal» lista las principales que tienen auxiliares. Con «cuenta_principal» devuelve el saldo de cada auxiliar y el total; si además se indica «auxiliar», agrega sus movimientos con saldo acumulado.')]
#[IsReadOnly]
class LibroAuxiliar extends AccountingTool
{
    public function handle(Request $request, LedgerService $ledger): ResponseFactory
    {
        $request->validate([
            'cuenta_principal' => ['nullable'],
            'auxiliar' => ['nullable'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        if (blank($request->get('cuenta_principal'))) {
            return $this->respond([
                'principales_con_auxiliares' => Account::where('is_auxiliary', false)
                    ->whereHas('auxiliaries')
                    ->orderBy('code')
                    ->get(),
            ]);
        }

        $principal = $this->findAccount($request->get('cuenta_principal'), 'cuenta_principal');
        $auxiliaries = $principal->auxiliaries()->orderBy('code')->get();

        $data = [
            'cuenta_principal' => $principal,
            'saldo_total' => $ledger->balanceBefore($principal),
            'auxiliares' => $auxiliaries->map(fn (Account $aux): array => [
                ...$this->accountRef($aux),
                'saldo' => $ledger->balanceBefore($aux),
            ]),
        ];

        if (filled($request->get('auxiliar'))) {
            $auxiliary = $this->findAccount($request->get('auxiliar'), 'auxiliar');

            if (! $auxiliaries->contains('id', $auxiliary->id)) {
                throw ValidationException::withMessages(['auxiliar' => "«{$auxiliary->code}» no es auxiliar de «{$principal->code}»."]);
            }

            $result = $ledger->movements($auxiliary, $request->get('desde'), $request->get('hasta'));
            $data['detalle'] = [
                'auxiliar' => $auxiliary,
                'saldo_inicial' => $result['opening'],
                'saldo_final' => $result['closing'],
                'movimientos' => $result['movements'],
            ];
        }

        return $this->respond($data);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'cuenta_principal' => $schema->string()->description('Código o id de la cuenta principal (p. ej. «1.1.03»). Opcional.'),
            'auxiliar' => $schema->string()->description('Código o id del auxiliar para ver sus movimientos. Opcional.'),
            'desde' => $schema->string()->description('Fecha inicial AAAA-MM-DD para el detalle (opcional).'),
            'hasta' => $schema->string()->description('Fecha final AAAA-MM-DD para el detalle (opcional).'),
        ];
    }
}
