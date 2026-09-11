<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Services\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Estados financieros')]
class Reports extends Component
{
    #[Url]
    public string $tab = 'balance';

    #[Url]
    public string $asOf = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->asOf = $this->asOf !== '' ? $this->asOf : now()->toDateString();
        $this->from = $this->from !== '' ? $this->from : now()->startOfYear()->toDateString();
        $this->to = $this->to !== '' ? $this->to : now()->toDateString();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['balance', 'pnl'], true) ? $tab : 'balance';
    }

    public function render(ReportService $reports)
    {
        $userId = auth()->id();

        return view('accounting::livewire.reports', [
            'balance' => $this->tab === 'balance'
                ? $reports->balanceSheet($userId, $this->asOf)
                : null,
            'pnl' => $this->tab === 'pnl'
                ? $reports->profitAndLoss($userId, $this->from !== '' ? $this->from : null, $this->to)
                : null,
        ]);
    }
}
