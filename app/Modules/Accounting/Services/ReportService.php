<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\PnlSection;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Shared\Money\Money;
use Illuminate\Support\Collection;

/**
 * Estados financieros por agregación sobre el diario.
 * Todos los montos como strings decimales (bcmath).
 */
class ReportService
{
    /**
     * Orden de presentación de la cascada del P&L:
     * ['section', clave, signo] intercalado con ['line'|'subline', clave, etiqueta, claveDeMargen|null].
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3?: ?string}>
     */
    public const CASCADE = [
        ['section', 'operating_income', '+'],
        ['section', 'cogs', '−'],
        ['line', 'gross', 'Utilidad Bruta', 'gross'],
        ['section', 'operating_expense', '−'],
        ['line', 'ebitda', 'EBITDA', 'ebitda'],
        ['section', 'depreciation', '−'],
        ['line', 'ebit', 'EBIT · Utilidad Operativa', 'ebit'],
        ['section', 'financial_income', '+'],
        ['section', 'financial_expense', '−'],
        ['subline', 'financial', 'Resultado financiero', null],
        ['section', 'other_income', '+'],
        ['section', 'other_expense', '−'],
        ['subline', 'other', 'Resultado no operativo', null],
        ['line', 'ebt', 'Resultado antes de impuestos', null],
        ['section', 'tax', '−'],
        ['line', 'net', 'Resultado Neto', 'net'],
    ];

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
    public function balanceSheet(int $userId, string $asOf, bool $includeZero = false): array
    {
        $balances = $this->balancesByAccount($userId, null, $asOf);

        $sections = [];
        foreach ([AccountType::Asset, AccountType::Liability, AccountType::Equity] as $type) {
            $sections[$type->value] = $this->buildSection(
                $this->accountsOfType($userId, $type),
                $balances,
                $includeZero,
                $type->label(),
            );
        }

        // Resultado acumulado (ingresos - gastos) hasta la fecha, como línea del patrimonio.
        $income = $this->buildSection($this->accountsOfType($userId, AccountType::Income), $balances, false, '')['total'];
        $expense = $this->buildSection($this->accountsOfType($userId, AccountType::Expense), $balances, false, '')['total'];
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
     * Estado de resultados multi-step del rango (ambos extremos inclusive):
     * Ingresos − Costo = Bruta; − Gastos op. = EBITDA; − D&A = EBIT;
     * ± financiero ± otros = EBT; − impuestos = Neto. Márgenes sobre
     * los ingresos operativos.
     *
     * @return array{
     *   from: ?string,
     *   to: string,
     *   sections: array<string, array{label: string, rows: array<int, array{account: Account, level: int, amount: string}>, total: string}>,
     *   lines: array{gross: string, ebitda: string, ebit: string, financial: string, other: string, ebt: string, net: string},
     *   margins: array{gross: ?string, ebitda: ?string, ebit: ?string, net: ?string},
     *   total_income: string,
     *   total_expense: string
     * }
     */
    public function profitAndLoss(int $userId, ?string $from, string $to, bool $includeZero = false): array
    {
        $balances = $this->balancesByAccount($userId, $from, $to);

        $bySection = Account::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereIn('type', [AccountType::Income, AccountType::Expense])
            ->orderBy('code')
            ->get()
            ->groupBy(fn (Account $a): string => $a->effectivePnlSection()->value);

        $sections = [];
        foreach (PnlSection::cases() as $section) {
            $sections[$section->value] = $this->buildSection(
                $bySection->get($section->value, collect()),
                $balances,
                $includeZero,
                $section->label(),
            );
        }

        $t = fn (PnlSection $s): string => $sections[$s->value]['total'];

        $operatingIncome = $t(PnlSection::OperatingIncome);
        $gross = bcsub($operatingIncome, $t(PnlSection::Cogs), 2);
        $ebitda = bcsub($gross, $t(PnlSection::OperatingExpense), 2);
        $ebit = bcsub($ebitda, $t(PnlSection::Depreciation), 2);
        $financial = bcsub($t(PnlSection::FinancialIncome), $t(PnlSection::FinancialExpense), 2);
        $other = bcsub($t(PnlSection::OtherIncome), $t(PnlSection::OtherExpense), 2);
        $ebt = Money::add($ebit, Money::add($financial, $other));
        $net = bcsub($ebt, $t(PnlSection::Tax), 2);

        $margin = fn (string $line): ?string => Money::isZero($operatingIncome)
            ? null
            : bcdiv(bcmul($line, '100', 4), $operatingIncome, 1);

        return [
            'from' => $from,
            'to' => $to,
            'cascade' => self::CASCADE,
            'sections' => $sections,
            'lines' => [
                'gross' => $gross,
                'ebitda' => $ebitda,
                'ebit' => $ebit,
                'financial' => $financial,
                'other' => $other,
                'ebt' => $ebt,
                'net' => $net,
            ],
            'margins' => [
                'gross' => $margin($gross),
                'ebitda' => $margin($ebitda),
                'ebit' => $margin($ebit),
                'net' => $margin($net),
            ],
            'total_income' => Money::add($operatingIncome, Money::add($t(PnlSection::FinancialIncome), $t(PnlSection::OtherIncome))),
            'total_expense' => array_reduce(
                [PnlSection::Cogs, PnlSection::OperatingExpense, PnlSection::Depreciation, PnlSection::FinancialExpense, PnlSection::Tax, PnlSection::OtherExpense],
                fn (string $carry, PnlSection $s): string => Money::add($carry, $t($s)),
                '0.00',
            ),
        ];
    }

    /**
     * @return Collection<int, Account>
     */
    private function accountsOfType(int $userId, AccountType $type): Collection
    {
        return Account::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->orderBy('code')
            ->get();
    }

    /**
     * Filas jerárquicas de un conjunto de cuentas: cada padre acumula sus
     * hijas. Los subárboles con saldo 0 se omiten salvo que $includeZero.
     * Las raíces son las cuentas cuyo padre no pertenece al conjunto.
     *
     * @param  Collection<int, Account>  $accounts
     * @param  array<int, array{debit: string, credit: string}>  $balances
     * @return array{label: string, rows: array<int, array{account: Account, level: int, amount: string}>, total: string}
     */
    private function buildSection(Collection $accounts, array $balances, bool $includeZero, string $label): array
    {
        $ids = $accounts->pluck('id')->flip();
        $byParent = $accounts->groupBy(
            fn (Account $a) => $a->parent_id !== null && $ids->has($a->parent_id) ? $a->parent_id : 0,
        );

        $rows = [];

        $walk = function (int $parentKey, int $level) use (&$walk, &$rows, $byParent, $balances, $includeZero): string {
            $sum = '0.00';

            foreach ($byParent->get($parentKey, collect()) as $account) {
                $own = $this->signedBalance($account->type, $balances[$account->id] ?? null);

                // Las auxiliares consolidan en su principal sin fila propia:
                // su detalle vive en los Libros auxiliares.
                if ($account->is_auxiliary) {
                    $sum = Money::add($sum, $own);

                    continue;
                }

                // Reservar la posición del padre antes de recorrer sus hijas.
                $index = count($rows);
                $rows[] = null;

                $children = $walk($account->id, $level + 1);
                $subtotal = Money::add($own, $children);

                if (! $includeZero && Money::isZero($subtotal) && Money::isZero($own)) {
                    // Sin saldo en todo el subárbol: quitar la fila reservada y sus hijas.
                    array_splice($rows, $index);
                } else {
                    $rows[$index] = ['account' => $account, 'level' => $level, 'amount' => $subtotal];
                }

                $sum = Money::add($sum, $subtotal);
            }

            return $sum;
        };

        $total = $walk(0, 0);

        return [
            'label' => $label,
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
