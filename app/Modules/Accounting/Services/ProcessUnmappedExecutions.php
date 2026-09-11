<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationExecution;

/**
 * Procesamiento retroactivo: al configurar un mapeo, genera los asientos
 * de las ejecuciones que quedaron sin mapear (o fallidas).
 */
class ProcessUnmappedExecutions
{
    public function __construct(
        private readonly PostExecutionToJournal $poster,
    ) {}

    /**
     * @return array{posted: int, failed: int}
     */
    public function process(int $userId, int $operationTypeId): array
    {
        $posted = 0;
        $failed = 0;

        OperationExecution::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('operation_type_id', $operationTypeId)
            ->whereIn('status', [ExecutionStatus::Pending, ExecutionStatus::Unmapped, ExecutionStatus::Failed])
            ->orderBy('executed_at')
            ->orderBy('id')
            ->get()
            ->each(function (OperationExecution $execution) use (&$posted, &$failed): void {
                $this->poster->post($execution);

                match ($execution->fresh()->status) {
                    ExecutionStatus::Posted => $posted++,
                    ExecutionStatus::Failed => $failed++,
                    default => null,
                };
            });

        return ['posted' => $posted, 'failed' => $failed];
    }
}
