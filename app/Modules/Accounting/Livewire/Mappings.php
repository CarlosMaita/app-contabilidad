<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationType;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mapeos contables')]
class Mappings extends Component
{
    public function render()
    {
        $types = OperationType::withCount([
            'executions as unposted_count' => fn ($q) => $q->whereIn('status', [
                ExecutionStatus::Pending,
                ExecutionStatus::Unmapped,
                ExecutionStatus::Failed,
            ]),
        ])->orderBy('name')->get();

        $activeMappings = AccountingMapping::where('is_active', true)
            ->whereIn('operation_type_id', $types->pluck('id'))
            ->get()
            ->keyBy('operation_type_id');

        return view('accounting::livewire.mappings', [
            'types' => $types,
            'activeMappings' => $activeMappings,
        ]);
    }
}
