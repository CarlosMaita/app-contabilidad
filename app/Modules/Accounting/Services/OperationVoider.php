<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Shared\Contracts\VoidsOperationExecutions;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OperationVoider implements VoidsOperationExecutions
{
    public function __construct(
        private readonly ReverseJournalEntry $reverser,
    ) {}

    public function voidExecution(int $executionId): void
    {
        // Con global scope: solo ejecuciones del usuario autenticado.
        $execution = OperationExecution::findOrFail($executionId);

        DB::transaction(function () use ($execution): void {
            if ($execution->status === ExecutionStatus::Posted) {
                $entry = JournalEntry::withoutGlobalScopes()
                    ->where('operation_execution_id', $execution->id)
                    ->firstOrFail();

                $this->reverser->reverse($entry);
            } elseif (! in_array($execution->status, [
                ExecutionStatus::Pending,
                ExecutionStatus::Unmapped,
                ExecutionStatus::Failed,
            ], true)) {
                throw new RuntimeException('Esta ejecución no se puede anular.');
            }

            $execution->update(['status' => ExecutionStatus::Voided]);
        });
    }
}
