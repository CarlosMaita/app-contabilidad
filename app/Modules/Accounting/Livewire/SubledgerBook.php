<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\LedgerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Libros auxiliares')]
class SubledgerBook extends Component
{
    #[Url]
    public string $principalId = '';

    #[Url]
    public string $auxiliaryId = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function updatedPrincipalId(): void
    {
        $this->auxiliaryId = '';
    }

    public function render(LedgerService $ledger)
    {
        $principals = Account::where('is_auxiliary', false)
            ->whereHas('auxiliaries')
            ->orderBy('code')
            ->get();

        $principal = $this->principalId !== ''
            ? $principals->firstWhere('id', (int) $this->principalId)
            : null;

        $auxiliaries = $principal !== null ? $principal->auxiliaries()->get() : collect();

        $summary = $auxiliaries->map(fn (Account $aux): array => [
            'account' => $aux,
            'balance' => $ledger->balanceBefore($aux),
        ]);

        $auxiliary = $this->auxiliaryId !== ''
            ? $auxiliaries->firstWhere('id', (int) $this->auxiliaryId)
            : null;

        return view('accounting::livewire.subledger-book', [
            'principals' => $principals,
            'principal' => $principal,
            'principalBalance' => $principal !== null ? $ledger->balanceBefore($principal) : null,
            'summary' => $summary,
            'auxiliary' => $auxiliary,
            'result' => $auxiliary !== null
                ? $ledger->movements($auxiliary, $this->from !== '' ? $this->from : null, $this->to !== '' ? $this->to : null)
                : null,
        ]);
    }
}
