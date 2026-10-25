<?php

namespace App\Books;

use App\Models\User;
use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BookTransfer;
use App\Modules\Accounting\Services\AccountManager;
use App\Modules\Accounting\Services\JournalEntryBuilder;
use App\Modules\Shared\Books\Book;
use App\Modules\Shared\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transferencias entre contabilidades (ej. aporte de la personal al fondo
 * familiar, o reintegro del fondo a la personal). Cada contabilidad recibe
 * su propio asiento balanceado; quedan enlazados en BookTransfer.
 *
 *   origen:  Debe contrapartida        / Haber cuenta de salida (efectivo)
 *   destino: Debe cuenta de entrada     / Haber contrapartida
 *
 * Contrapartidas por defecto: en una compartida, el auxiliar «Aportes de
 * {persona}»; en la personal, «Aportes a {destino}» (gasto) al enviar y
 * «Reintegros de {origen}» (ingreso) al recibir.
 */
class BookTransferService
{
    public function __construct(
        private readonly JournalEntryBuilder $journal,
        private readonly BookService $books,
    ) {}

    /**
     * @param  array{from: Book, from_cash: int, from_counter?: ?int, to: Book, to_cash: int, to_counter?: ?int, amount: string, date: string, description: string}  $data
     */
    public function transfer(User $user, array $data): BookTransfer
    {
        $from = $data['from'];
        $to = $data['to'];

        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to' => 'Elegí una contabilidad de destino distinta a la de origen.']);
        }
        foreach ([$from, $to] as $book) {
            abort_unless($book->hasMember($user), 403, 'No sos miembro de esa contabilidad.');
        }
        if (! is_numeric($data['amount']) || Money::isZero(Money::normalize($data['amount'])) || Money::isNegative(Money::normalize($data['amount']))) {
            throw ValidationException::withMessages(['amount' => 'El monto debe ser mayor a cero.']);
        }

        $amount = Money::normalize($data['amount']);

        return DB::transaction(function () use ($user, $data, $from, $to, $amount): BookTransfer {
            $fromCounter = $data['from_counter'] ?? null ?: $this->defaultCounter($from, $to, $user, outgoing: true)->id;
            $toCounter = $data['to_counter'] ?? null ?: $this->defaultCounter($to, $from, $user, outgoing: false)->id;

            $this->assertAccountIn($from, $data['from_cash'], 'from_cash');
            $this->assertAccountIn($from, $fromCounter, 'from_counter');
            $this->assertAccountIn($to, $data['to_cash'], 'to_cash');
            $this->assertAccountIn($to, $toCounter, 'to_counter');

            $description = trim($data['description']);

            $out = $this->journal->post(
                bookId: $from->id,
                date: $data['date'],
                description: "{$description} → {$to->label()}",
                lines: [
                    ['side' => EntrySide::Debit, 'account_id' => $fromCounter, 'amount' => $amount, 'memo' => null],
                    ['side' => EntrySide::Credit, 'account_id' => $data['from_cash'], 'amount' => $amount, 'memo' => null],
                ],
            );

            $in = $this->journal->post(
                bookId: $to->id,
                date: $data['date'],
                description: "{$description} ← {$from->label()} ({$user->name})",
                lines: [
                    ['side' => EntrySide::Debit, 'account_id' => $data['to_cash'], 'amount' => $amount, 'memo' => null],
                    ['side' => EntrySide::Credit, 'account_id' => $toCounter, 'amount' => $amount, 'memo' => null],
                ],
            );

            return BookTransfer::create([
                'user_id' => $user->id,
                'from_book_id' => $from->id,
                'to_book_id' => $to->id,
                'from_entry_id' => $out->id,
                'to_entry_id' => $in->id,
                'amount' => $amount,
                'date' => $data['date'],
                'description' => $description,
            ]);
        });
    }

    /** Contrapartida sugerida (se crea si falta). */
    public function defaultCounter(Book $book, Book $other, User $user, bool $outgoing): Account
    {
        if (! $book->isPersonal()) {
            $contribution = $this->books->ensureContributionAccount($book, $user);
            if ($contribution !== null) {
                return $contribution;
            }
        }

        [$parentCode, $name] = $outgoing
            ? [$book->template === 'household' ? '6.7' : '6.5', "Aportes a {$other->label()}"]
            : [$book->template === 'household' ? '4.2' : '4.3', "Reintegros de {$other->label()}"];

        $existing = Account::withoutGlobalScopes()->where('book_id', $book->id)->where('name', $name)->first();
        if ($existing !== null) {
            return $existing;
        }

        $parent = Account::withoutGlobalScopes()->where('book_id', $book->id)->where('code', $parentCode)->first()
            ?? throw ValidationException::withMessages([
                $outgoing ? 'from_counter' : 'to_counter' => 'Elegí la cuenta de contrapartida en «'.$book->label().'».',
            ]);

        $codes = Account::withoutGlobalScopes()->where('book_id', $book->id)->where('parent_id', $parent->id)->pluck('code');
        $next = 1;
        while ($codes->contains(sprintf('%s.%02d', $parentCode, $next))) {
            $next++;
        }

        return InBook::run($book, $user, fn () => app(AccountManager::class)->save([
            'code' => sprintf('%s.%02d', $parentCode, $next),
            'name' => $name,
            'type' => $parent->type->value,
            'parent_id' => $parent->id,
            'is_postable' => true,
            'is_auxiliary' => false,
            'is_active' => true,
        ]));
    }

    private function assertAccountIn(Book $book, int $accountId, string $field): void
    {
        $account = Account::withoutGlobalScopes()->where('book_id', $book->id)->find($accountId);

        if ($account === null || ! $account->is_postable || ! $account->is_active) {
            throw ValidationException::withMessages([$field => 'La cuenta no pertenece a «'.$book->label().'» o no es imputable.']);
        }
    }
}
