<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Shared\Money\Money;

/**
 * Estados financieros por agregación sobre el diario.
 * Todos los montos como strings decimales (bcmath).
 */
class ReportService
{
    /**
     * Balance general a una fecha (inclusive).
     *
     * @return array{
     *   as_of: string,
     *   sections: array<string, array{label: string, rows: array<int, array{account: Account, level: int, amount: string}>, total: string}>,
     *   result: string,
     *   check: array{assets: string, liabilities_equity: string, balanced: bool}
     * }
     */
    public function balanceSheet(int $userId, string $asOf): array
    {
        $balances = $this->balancesByAccount($userId, null, $asOf);

        $sections = [];
        foreach ([AccountType::Asset, AccountType::Liability, AccountType::Equity] as $type) {
            $sections[$type->value] = $this->section($userId, $type, $balances);
        }

        // Resultado acumulado (ingresos - gastos) hasta la fecha, como línea del patrimonio.
        $income = $this->section($userId, AccountType::Income, $balances)['total'];
        $expense = $this->section($userId, AccountType::Expense, $balances)['total'];
        $result = bcsub($income, $expense, 2);

        $liabilitiesEquity = Money::add(
            $sections['liability']['total'],
            Money::add($sections['equity']['total'], $result),
        );

        return [
            'as_of' => $asOf,
            'sections' => $sections,
            'result' => $result,
            'check' => [
                'assets' => $sections['asset']['total'],
                'liabilities_equity' => $liabilitiesEquity,
                'balanced' => Money::equals($sections['asset']['total'], $liabilitiesEquity),
            ],
        ];
    }

    /**
     * Estado de resultados del rango (ambos extremos inclusive).
     *
     * @return array{
     *   from: ?string,
     *   to: string,
     *   income: array{label: string, rows: array<int, array{account: Account, level: int, amount: string}>, total: string},
     *   expense: array{label: string, rows: array<int, array{account: Account, level: int, amount: string}>, total: string},
     *   result: string
     * }
     */
    public function profitAndLoss(int $userId, ?string $from, string $to): array
    {
        $balances = $this->balancesByAccount($userId, $from, $to);

        $income = $this->section($userId, AccountType::Income, $balances);
        $expense = $this->section($userId, AccountType::Expense, $balances);

        return [
            'from' => $from,
            'to' => $to,
            'income' => $income,
            'expense' => $expense,
            'result' => bcsub($income['total'], $expense['total'], 2),
        ];
    }

    /**
     * Filas jerárquicas de un tipo de cuenta: cada padre acumula sus hijas.
     * Se omiten las cuentas con saldo 0 en todo su subárbol.
     *
     * @param  array<int, array{debit: string, credit: string}>  $balances
     * @return array{label: string, rows: array<int, array{account: Account, level: int, amount: string}>, total: string}
     */
    private function section(int $userId, AccountType $type, array $balances): array
    {
        $accounts = Account::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->orderBy('code')
            ->get();

        $byParent = $accounts->groupBy('parent_id');

        $rows = [];
        $total = '0.00';

        $walk = function (?int $parentId, int $level) use (&$walk, &$rows, $byParent, $balances, $type): string {
            $sum = '0.00';

            foreach ($byParent->get($parentId, collect()) as $account) {
                $own = $this->signedBalance($type, $balances[$account->id] ?? null);

                // Reservar la posición del padre antes de recorrer sus hijas.
                $index = count($rows);
                $rows[] = null;

                $children = $walk($account->id, $level + 1);
                $subtotal = Money::add($own, $children);

                if (Money::isZero($subtotal) && Money::isZero($own)) {
                    // Sin saldo en todo el subárbol: quitar la fila reservada y sus hijas.
                    array_splice($rows, $index);
                } else {
                    $rows[$index] = ['account' => $account, 'level' => $level, 'amount' => $subtotal];
                }

                $sum = Money::add($sum, $subtotal);
            }

            return $sum;
        };

        $total = $walk(null, 0);

        return [
            'label' => $type->label(),
            'rows' => array_values(array_filter($rows, fn ($r): bool => $r !== null)),
            'total' => $total,
        ];
    }

    /**
     * Sumas de debe/haber por cuenta en el rango, en una sola consulta.
     *
     * @return array<int, array{debit: string, credit: string}>
     */
    private function balancesByAccount(int $userId, ?string $from, ?string $to): array
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.user_id', $userId)
            ->when($from !== null, fn ($q) => $q->whereDate('journal_entries.date', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('journal_entries.date', '<=', $to))
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->account_id => [
                    'debit' => Money::normalize((string) $row->total_debit),
                    'credit' => Money::normalize((string) $row->total_credit),
                ],
            ])
            ->all();
    }

    /**
     * @param  array{debit: string, credit: string}|null  $sums
     */
    private function signedBalance(AccountType $type, ?array $sums): string
    {
        if ($sums === null) {
            return '0.00';
        }

        return $type->isDebitNature()
            ? bcsub($sums['debit'], $sums['credit'], 2)
            : bcsub($sums['credit'], $sums['debit'], 2);
    }
}
