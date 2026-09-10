<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Shared\Contracts\ChartOfAccountsProvider;
use Illuminate\Support\Collection;

class EloquentChartOfAccountsProvider implements ChartOfAccountsProvider
{
    public function postableAccounts(): Collection
    {
        return Account::postable()
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $a): object => (object) [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
            ]);
    }
}
