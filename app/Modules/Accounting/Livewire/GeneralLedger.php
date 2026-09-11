<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\LedgerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Libro mayor')]
class GeneralLedger extends Component
{
    #[Url]
    public string $accountId = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function render(LedgerService $ledger)
    {
        $account = $this->accountId !== ''
            ? Account::find((int) $this->accountId)
            : null;

        $result = $account !== null
            ? $ledger->movements(
                $account,
                $this->from !== '' ? $this->from : null,
                $this->to !== '' ? $this->to : null,
            )
            : null;

        return view('accounting::livewire.general-ledger', [
            // Imputables no auxiliares + principales que consolidan auxiliares.
            'accounts' => Account::where('is_auxiliary', false)
                ->where(fn ($q) => $q->where('is_postable', true)->orWhereHas('auxiliaries'))
                ->orderBy('code')
                ->get(),
            'account' => $account,
            'result' => $result,
        ]);
    }
}
