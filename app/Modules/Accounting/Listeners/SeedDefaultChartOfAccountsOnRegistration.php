<?php

namespace App\Modules\Accounting\Listeners;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\DefaultChartOfAccounts;
use Illuminate\Auth\Events\Registered;

class SeedDefaultChartOfAccountsOnRegistration
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $alreadyHasAccounts = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->exists();

        if (! $alreadyHasAccounts) {
            DefaultChartOfAccounts::seedFor($user);
        }
    }
}
