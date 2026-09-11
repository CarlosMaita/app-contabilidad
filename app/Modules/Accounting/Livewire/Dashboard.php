<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\ReportService;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationExecution;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(ReportService $reports)
    {
        $userId = auth()->id();
        $today = now()->toDateString();

        $balance = $reports->balanceSheet($userId, $today);
        $month = $reports->profitAndLoss($userId, now()->startOfMonth()->toDateString(), $today);

        $attention = OperationExecution::whereIn('status', [
            ExecutionStatus::Unmapped,
            ExecutionStatus::Failed,
            ExecutionStatus::Pending,
        ])->count();

        return view('accounting::livewire.dashboard', [
            'assets' => $balance['sections']['asset']['total'],
            'balanced' => $balance['check']['balanced'],
            'monthIncome' => $month['income']['total'],
            'monthExpense' => $month['expense']['total'],
            'monthResult' => $month['result'],
            'attention' => $attention,
            'lastEntries' => JournalEntry::with('execution.operationType')
                ->orderByDesc('date')
                ->orderByDesc('number')
                ->limit(5)
                ->get(),
        ]);
    }
}
