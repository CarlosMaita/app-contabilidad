<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Events\JournalEntryPosted;
use App\Modules\Accounting\Exceptions\MappingResolutionException;
use App\Modules\Accounting\Exceptions\UnbalancedEntryException;
use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationExecution;

/**
 * Convierte una ejecución de operación en un asiento del diario.
 * Corre sin usuario autenticado (listener en cola), por eso trabaja
 * siempre sin global scopes y con el user_id de la ejecución.
 */
class PostExecutionToJournal
{
    public function __construct(
        private readonly MappingResolver $resolver,
        private readonly JournalEntryBuilder $builder,
    ) {}

    public function post(OperationExecution $execution): void
    {
        $execution = OperationExecution::withoutGlobalScopes()
            ->with('operationType')
            ->findOrFail($execution->id);

        // Idempotencia: un evento genera como máximo un asiento.
        $alreadyPosted = JournalEntry::withoutGlobalScopes()
            ->where('operation_execution_id', $execution->id)
            ->exists();

        if ($alreadyPosted || ! in_array($execution->status, [
            ExecutionStatus::Pending,
            ExecutionStatus::Unmapped,
            ExecutionStatus::Failed,
        ], true)) {
            return;
        }

        $mapping = AccountingMapping::withoutGlobalScopes()
            ->where('user_id', $execution->user_id)
            ->where('operation_type_id', $execution->operation_type_id)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->with('lines')
            ->first();

        if ($mapping === null) {
            $execution->update(['status' => ExecutionStatus::Unmapped, 'error_message' => null]);

            return;
        }

        try {
            $resolved = $this->resolver->resolve($mapping, $execution);

            $entry = $this->builder->post(
                userId: $execution->user_id,
                date: $execution->executed_at,
                description: $resolved['description'],
                lines: $resolved['lines'],
                operationExecutionId: $execution->id,
                mappingId: $mapping->id,
            );

            $execution->update(['status' => ExecutionStatus::Posted, 'error_message' => null]);

            JournalEntryPosted::dispatch($entry);
        } catch (MappingResolutionException|UnbalancedEntryException $e) {
            $execution->update([
                'status' => ExecutionStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
