<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Services\JournalEntryBuilder;
use App\Modules\Shared\Money\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('registrar_asiento')]
#[Description('Registra un asiento manual de partida doble (ajustes, aperturas, correcciones). Las líneas deben sumar lo mismo en debe y en haber, y cada cuenta debe ser imputable y activa. El asiento queda inmutable: para corregirlo se revierte. Para hechos que tienen un tipo de operación, preferí «ejecutar_operacion».')]
class RegistrarAsiento extends AccountingTool
{
    public function handle(Request $request, JournalEntryBuilder $builder): Response|ResponseFactory
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'descripcion' => ['required', 'string', 'max:500'],
            'lineas' => ['required', 'array', 'min:2'],
            'lineas.*.lado' => ['required', 'in:debe,haber'],
            'lineas.*.cuenta' => ['required'],
            'lineas.*.monto' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'lineas.*.memo' => ['nullable', 'string', 'max:255'],
        ], [], [
            'lineas.*.lado' => 'lado',
            'lineas.*.cuenta' => 'cuenta',
            'lineas.*.monto' => 'monto',
        ]);

        $lines = [];
        foreach ($validated['lineas'] as $i => $line) {
            $account = $this->findAccount($line['cuenta'], "lineas.{$i}.cuenta");

            if (! $account->is_postable || ! $account->is_active) {
                throw ValidationException::withMessages([
                    "lineas.{$i}.cuenta" => "La cuenta «{$account->code} {$account->name}» no es imputable (tiene sub-cuentas o está inactiva). Usá una de sus sub-cuentas.",
                ]);
            }

            $lines[] = [
                'side' => $line['lado'] === 'debe' ? EntrySide::Debit : EntrySide::Credit,
                'account_id' => $account->id,
                'amount' => Money::normalize((string) $line['monto']),
                'memo' => filled($line['memo'] ?? null) ? $line['memo'] : null,
            ];
        }

        return $this->attempt(function () use ($builder, $validated, $lines) {
            $entry = $builder->post(
                userId: auth()->id(),
                date: $validated['fecha'],
                description: $validated['descripcion'],
                lines: $lines,
            );

            return $this->respond([
                'mensaje' => "Asiento #{$entry->number} registrado.",
                'asiento' => $this->entryData($entry->fresh(['lines.account', 'execution.operationType', 'reverses'])),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fecha' => $schema->string()->description('Fecha contable AAAA-MM-DD.')->required(),
            'descripcion' => $schema->string()->description('Descripción del asiento.')->required(),
            'lineas' => $schema->array()->min(2)->items($schema->object([
                'lado' => $schema->string()->enum(['debe', 'haber'])->required(),
                'cuenta' => $schema->string()->description('Código (p. ej. «6.1.01») o id de una cuenta imputable.')->required(),
                'monto' => $schema->number()->description('Monto positivo con hasta 2 decimales, punto como separador.')->required(),
                'memo' => $schema->string()->description('Detalle de la línea (opcional).'),
            ]))->description('Al menos dos líneas; total debe = total haber.')->required(),
        ];
    }
}
