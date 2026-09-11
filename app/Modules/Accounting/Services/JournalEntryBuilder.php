<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Exceptions\MappingResolutionException;
use App\Modules\Accounting\Exceptions\UnbalancedEntryException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Shared\Money\Money;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class JournalEntryBuilder
{
    /**
     * Registra un asiento balanceado con numeración correlativa por usuario.
     * Todo dentro de una transacción; nunca modifica asientos existentes.
     *
     * @param  array<int, array{side: EntrySide, account_id: int, amount: string, memo: ?string}>  $lines
     */
    public function post(
        int $userId,
        DateTimeInterface|string $date,
        string $description,
        array $lines,
        ?int $operationExecutionId = null,
        ?int $mappingId = null,
        ?int $reversesEntryId = null,
    ): JournalEntry {
        if ($lines === []) {
            throw new UnbalancedEntryException('El asiento no tiene líneas (todas evaluaron a 0).');
        }

        $this->assertAccountsArePostable($userId, array_column($lines, 'account_id'));

        [$totalDebit, $totalCredit] = $this->totals($lines);

        if (! Money::equals($totalDebit, $totalCredit)) {
            throw new UnbalancedEntryException(
                "El asiento no balancea: Debe {$totalDebit} ≠ Haber {$totalCredit}.",
            );
        }

        if (Money::isZero($totalDebit)) {
            throw new UnbalancedEntryException('El asiento está en cero.');
        }

        return DB::transaction(function () use ($userId, $date, $description, $lines, $operationExecutionId, $mappingId, $reversesEntryId): JournalEntry {
            $lastNumber = (int) JournalEntry::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->orderByDesc('number')
                ->lockForUpdate()
                ->value('number');

            $entry = JournalEntry::withoutGlobalScopes()->create([
                'user_id' => $userId,
                'number' => $lastNumber + 1,
                'date' => $date,
                'description' => $description,
                'operation_execution_id' => $operationExecutionId,
                'accounting_mapping_id' => $mappingId,
                'reverses_entry_id' => $reversesEntryId,
                'status' => JournalEntryStatus::Posted,
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['side'] === EntrySide::Debit ? $line['amount'] : '0.00',
                    'credit' => $line['side'] === EntrySide::Credit ? $line['amount'] : '0.00',
                    'memo' => $line['memo'],
                ]);
            }

            return $entry;
        });
    }

    /**
     * @param  array<int, int>  $accountIds
     */
    private function assertAccountsArePostable(int $userId, array $accountIds): void
    {
        $accounts = Account::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereIn('id', array_unique($accountIds))
            ->get()
            ->keyBy('id');

        foreach (array_unique($accountIds) as $id) {
            $account = $accounts->get($id);

            if ($account === null) {
                throw new MappingResolutionException("La cuenta #{$id} no existe o no pertenece al usuario.");
            }

            if (! $account->is_postable || ! $account->is_active) {
                throw new MappingResolutionException(
                    "La cuenta {$account->code} {$account->name} no es imputable o está inactiva.",
                );
            }
        }
    }

    /**
     * @param  array<int, array{side: EntrySide, amount: string}>  $lines
     * @return array{0: string, 1: string}
     */
    private function totals(array $lines): array
    {
        $debit = '0.00';
        $credit = '0.00';

        foreach ($lines as $line) {
            if ($line['side'] === EntrySide::Debit) {
                $debit = Money::add($debit, $line['amount']);
            } else {
                $credit = Money::add($credit, $line['amount']);
            }
        }

        return [$debit, $credit];
    }
}
