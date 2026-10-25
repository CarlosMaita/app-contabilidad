<?php

namespace App\Modules\Accounting\Console;

use App\Models\User;
use App\Modules\Accounting\Services\DefaultChartOfAccounts;
use App\Modules\Shared\Books\Book;
use Illuminate\Console\Command;

class SyncChartOfAccounts extends Command
{
    protected $signature = 'accounting:sync-chart {--user= : Solo las contabilidades de este usuario (id o email)}';

    protected $description = 'Agrega a las contabilidades con plantilla Negocio las cuentas del plan base que les falten y clasifica sus cuentas de balance para el flujo de efectivo (idempotente)';

    public function handle(): int
    {
        $books = Book::query()
            ->where('template', 'business')
            ->when($this->option('user'), function ($q, string $user): void {
                $userId = is_numeric($user) ? (int) $user : User::where('email', $user)->value('id');
                $q->whereHas('members', fn ($m) => $m->whereKey($userId));
            })
            ->with('owner')
            ->get();

        if ($books->isEmpty()) {
            $this->warn('No se encontraron contabilidades.');

            return self::FAILURE;
        }

        foreach ($books as $book) {
            $added = DefaultChartOfAccounts::syncFor($book);
            $classified = DefaultChartOfAccounts::classifyFor($book);

            $this->line("{$book->label()} ({$book->owner?->email}): {$added} cuenta(s) agregada(s), {$classified} clasificada(s) para el flujo de efectivo");
        }

        return self::SUCCESS;
    }
}
