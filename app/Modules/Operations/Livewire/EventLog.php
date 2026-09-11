<?php

namespace App\Modules\Operations\Livewire;

use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Shared\Contracts\VoidsOperationExecutions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Log de eventos')]
class EventLog extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $typeId = '';

    public string $from = '';

    public string $to = '';

    public ?int $expandedId = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'typeId', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function void(int $executionId): void
    {
        try {
            app(VoidsOperationExecutions::class)->voidExecution($executionId);
            session()->flash('status', 'Operación anulada (con contra-asiento si estaba contabilizada).');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $executions = OperationExecution::with('operationType')
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->typeId !== '', fn ($q) => $q->where('operation_type_id', (int) $this->typeId))
            ->when($this->from !== '', fn ($q) => $q->whereDate('executed_at', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('executed_at', '<=', $this->to))
            ->orderByDesc('executed_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('operations::livewire.event-log', [
            'executions' => $executions,
            'types' => OperationType::orderBy('name')->get(),
            'statuses' => ExecutionStatus::cases(),
        ]);
    }
}
