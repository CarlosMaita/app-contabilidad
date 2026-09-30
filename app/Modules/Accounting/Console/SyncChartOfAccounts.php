<?php

namespace App\Modules\Accounting\Console;

use App\Models\User;
use App\Modules\Accounting\Services\DefaultChartOfAccounts;
use Illuminate\Console\Command;

class SyncChartOfAccounts extends Command
{
    protected $signature = 'accounting:sync-chart {--user= : Solo este usuario (id o email)}';

    protected $description = 'Agrega a los usuarios existentes las cuentas del plan base que les falten y clasifica sus cuentas de balance para el flujo de efectivo (idempotente)';

    public function handle(): int
    {
        $users = User::query()
            ->when($this->option('user'), function ($q, string $user): void {
                is_numeric($user)
                    ? $q->whereKey((int) $user)
                    : $q->where('email', $user);
            })
            ->get();

        if ($users->isEmpty()) {
            $this->warn('No se encontraron usuarios.');

            return self::FAILURE;
        }

        foreach ($users as $user) {
            $added = DefaultChartOfAccounts::syncFor($user);
            $classified = DefaultChartOfAccounts::classifyFor($user);
            $this->line("{$user->email}: {$added} cuenta(s) agregada(s), {$classified} clasificada(s) para el flujo de efectivo");
        }

        return self::SUCCESS;
    }
}
