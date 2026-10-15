<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationExecution;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('log_de_eventos')]
#[Description('Ejecuciones de operaciones (log de eventos), de la más reciente a la más antigua, con su estado (posted, unmapped, failed, pending, voided), datos cargados, error y número de asiento generado.')]
#[IsReadOnly]
class LogDeEventos extends AccountingTool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'estado' => ['nullable', Rule::enum(ExecutionStatus::class)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $executions = OperationExecution::with('operationType')
            ->when($validated['estado'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['desde'] ?? null, fn ($q, $from) => $q->whereDate('executed_at', '>=', $from))
            ->when($validated['hasta'] ?? null, fn ($q, $to) => $q->whereDate('executed_at', '<=', $to))
            ->orderByDesc('executed_at')
            ->orderByDesc('id')
            ->limit($validated['limite'] ?? 50)
            ->get();

        $entries = JournalEntry::whereIn('operation_execution_id', $executions->pluck('id'))
            ->pluck('number', 'operation_execution_id');

        return $this->respond([
            'eventos' => $executions->map(fn (OperationExecution $e): array => [
                'id' => $e->id,
                'tipo' => $e->operationType?->code,
                'nombre_tipo' => $e->operationType?->name,
                'fecha' => $e->executed_at->toDateString(),
                'estado' => $e->status->value,
                'descripcion' => $e->description,
                'datos' => $e->payload,
                'error' => $e->error_message,
                'asiento' => $entries[$e->id] ?? null,
            ]),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'estado' => $schema->string()->enum(array_column(ExecutionStatus::cases(), 'value'))->description('Filtra por estado (opcional).'),
            'desde' => $schema->string()->description('Fecha inicial AAAA-MM-DD (opcional).'),
            'hasta' => $schema->string()->description('Fecha final AAAA-MM-DD (opcional).'),
            'limite' => $schema->integer()->description('Máximo de eventos, hasta 200 (por defecto 50).'),
        ];
    }
}
