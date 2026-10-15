<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Operations\Actions\ExecuteOperation;
use App\Modules\Operations\Enums\VariableType;
use App\Modules\Operations\Models\OperationType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('ejecutar_operacion')]
#[Description('Ejecuta un tipo de operación (venta, cobro, pago…) con sus datos: queda en el log de eventos y el mapeo contable genera el asiento automáticamente. Consultá «tipos_de_operacion» para ver el código y las variables. Las variables de tipo cuenta aceptan el código de la cuenta. Si el tipo no tiene mapeo, el evento queda «unmapped» sin asiento.')]
class EjecutarOperacion extends AccountingTool
{
    public function handle(Request $request, ExecuteOperation $execute): Response|ResponseFactory
    {
        $validated = $request->validate([
            'tipo' => ['required', 'string'],
            'datos' => ['present', 'array'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ]);

        $type = OperationType::with('variables')->where('code', $validated['tipo'])->first()
            ?? throw ValidationException::withMessages(['tipo' => "No existe el tipo de operación «{$validated['tipo']}». Consultá «tipos_de_operacion»."]);

        if (! $type->is_active) {
            throw ValidationException::withMessages(['tipo' => "El tipo de operación «{$type->name}» está inactivo."]);
        }

        // Las variables de tipo cuenta se envían por código; el payload guarda el id.
        $payload = $validated['datos'];
        foreach ($type->variables as $variable) {
            if ($variable->type === VariableType::Account && filled($payload[$variable->name] ?? null)) {
                $payload[$variable->name] = $this->findAccount($payload[$variable->name], "datos.{$variable->name}")->id;
            }
        }

        return $this->attempt(function () use ($execute, $type, $payload, $validated) {
            $execution = $execute->execute($type, $payload, $validated['fecha'], $validated['descripcion'] ?? null)->fresh();

            $entry = JournalEntry::with(['lines.account', 'execution.operationType', 'reverses'])
                ->where('operation_execution_id', $execution->id)
                ->first();

            return $this->respond([
                'evento_id' => $execution->id,
                'estado' => $execution->status->value,
                'error' => $execution->error_message,
                'asiento' => $entry !== null ? $this->entryData($entry) : null,
                'mensaje' => match (true) {
                    $entry !== null => "Operación registrada: asiento #{$entry->number}.",
                    $execution->status->value === 'unmapped' => 'Operación registrada sin asiento: el tipo no tiene mapeo contable.',
                    default => "Operación registrada con estado «{$execution->status->value}».",
                },
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tipo' => $schema->string()->description('Código del tipo de operación (ver «tipos_de_operacion»).')->required(),
            'datos' => $schema->object()->description('Valores por nombre de variable, p. ej. {"cliente": "1.1.03.01", "neto": 1500.50}.')->required(),
            'fecha' => $schema->string()->description('Fecha contable AAAA-MM-DD.')->required(),
            'descripcion' => $schema->string()->description('Descripción del evento (opcional).'),
        ];
    }
}
