<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\ReverseJournalEntry;
use App\Modules\Shared\Contracts\VoidsOperationExecutions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('revertir_asiento')]
#[Description('Revierte un asiento registrando un contra-asiento (los asientos nunca se editan ni borran). Si el asiento proviene de una operación, además anula esa ejecución en el log de eventos.')]
#[IsDestructive]
class RevertirAsiento extends AccountingTool
{
    public function handle(Request $request, ReverseJournalEntry $reverser, VoidsOperationExecutions $voider): Response|ResponseFactory
    {
        $request->validate(['numero' => ['required', 'integer', 'min:1']]);

        return $this->attempt(function () use ($request, $reverser, $voider) {
            $entry = JournalEntry::where('number', $request->get('numero'))->firstOrFail();

            if ($entry->operation_execution_id !== null) {
                // Mantiene consistente el log de eventos: anula la ejecución.
                $voider->voidExecution($entry->operation_execution_id);
                $reversal = JournalEntry::where('reverses_entry_id', $entry->id)->firstOrFail();
            } else {
                $reversal = $reverser->reverse($entry);
            }

            return $this->respond([
                'mensaje' => "Asiento #{$entry->number} revertido con el contra-asiento #{$reversal->number}"
                    .($entry->operation_execution_id !== null ? ' y operación de origen anulada.' : '.'),
                'contra_asiento' => $this->entryData($reversal->fresh(['lines.account', 'execution.operationType', 'reverses'])),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'numero' => $schema->integer()->description('Número del asiento a revertir.')->required(),
        ];
    }
}
