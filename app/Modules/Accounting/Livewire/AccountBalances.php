<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Services\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Saldos de cuentas')]
class AccountBalances extends Component
{
    #[Url]
    public string $asOf = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->asOf = $this->asOf !== '' ? $this->asOf : now()->toDateString();
    }

    public function render(ReportService $reports)
    {
        return view('accounting::livewire.account-balances', [
            'report' => $reports->accountBalances(
                auth()->id(),
                $this->asOf !== '' ? $this->asOf : now()->toDateString(),
                $this->search,
            ),
        ]);
    }
}
