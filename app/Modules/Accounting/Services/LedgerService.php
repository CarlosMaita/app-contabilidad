<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Shared\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Saldos y movimientos por cuenta, calculados por agregación sobre el
 * diario. El saldo se expresa según la naturaleza de la cuenta:
 * deudora (activo/gasto) = debe - haber; acreedora = haber - debe.
 */
class LedgerService
{
    /**
     * Saldo de la cuenta hasta una fecha exclusive (o histórico completo).
     */
    public function balanceBefore(Account $account, ?string $date = null): string
    {
        $totals = $this->linesQuery($account, until: $date)
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        return $this->signed($account, (string) $totals->total_debit, (string) $totals->total_credit);
    }

    /**
     * Movimientos de la cuenta en el rango, con saldo acumulado.
     *
     * @return array{opening: string, closing: string, movements: Collection<int, object>}
     */
    public function movements(Account $account, ?string $from = null, ?string $to = null): array
    {
        $opening = $from !== null ? $this->balanceBefore($account, $from) : '0.00';

        $lines = $this->linesQuery($account, from: $from, to: $to)
            ->with('entry')
            ->get()
            ->sortBy(fn (JournalLine $line): array => [$line->entry->date->toDateString(), $line->entry->number, $line->id])
            ->values();

        $running = $opening;

        $movements = $lines->map(function (JournalLine $line) use ($account, &$running): object {
            $running = Money::add($running, $this->signed($account, (string) $line->debit, (string) $line->credit));

            return (object) [
                'date' => $line->entry->date,
                'number' => $line->entry->number,
                'description' => $line->entry->description,
                'memo' => $line->memo,
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'balance' => $running,
            ];
        });

        return ['opening' => $opening, 'closing' => $running, 'movements' => $movements];
    }

    /**
     * @return Builder<JournalLine>
     */
    private function linesQuery(Account $account, ?string $from = null, ?string $to = null, ?string $until = null): Builder
    {
        // Una cuenta principal con auxiliares agrega los movimientos de todas.
        $accountIds = Account::withoutGlobalScopes()
            ->where('parent_id', $account->id)
            ->where('is_auxiliary', true)
            ->pluck('id')
            ->prepend($account->id)
            ->all();

        return JournalLine::query()
            ->whereIn('account_id', $accountIds)
            ->whereHas('entry', function (Builder $q) use ($account, $from, $to, $until): void {
                $q->withoutGlobalScopes()
                    ->where('user_id', $account->user_id)
                    ->when($until !== null, fn (Builder $q) => $q->whereDate('date', '<', $until))
                    ->when($from !== null, fn (Builder $q) => $q->whereDate('date', '>=', $from))
                    ->when($to !== null, fn (Builder $q) => $q->whereDate('date', '<=', $to));
            });
    }

    private function signed(Account $account, string $debit, string $credit): string
    {
        $debit = Money::normalize($debit);
        $credit = Money::normalize($credit);

        return $account->type->isDebitNature()
            ? bcsub($debit, $credit, 2)
            : bcsub($credit, $debit, 2);
    }
}
