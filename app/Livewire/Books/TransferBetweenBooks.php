<?php

namespace App\Livewire\Books;

use App\Books\BookTransferService;
use App\Models\User;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\CashFlowSection;
use App\Modules\Accounting\Models\Account;
use App\Modules\Shared\Books\Book;
use App\Modules\Shared\Books\CurrentBook;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Transferir entre contabilidades')]
class TransferBetweenBooks extends Component
{
    #[Url(as: 'from')]
    public string $fromBookId = '';

    #[Url(as: 'to')]
    public string $toBookId = '';

    public string $fromCash = '';

    public string $toCash = '';

    public string $fromCounter = '';

    public string $toCounter = '';

    public string $amount = '';

    public string $date = '';

    public string $description = 'Aporte';

    public function mount(): void
    {
        $books = $this->books();

        // Por defecto: sale de la personal y entra en la actual (o la primera compartida).
        if (! $books->contains('id', (int) $this->fromBookId)) {
            $this->fromBookId = (string) $this->user()->personalBook()->id;
        }
        if (! $books->contains('id', (int) $this->toBookId) || $this->toBookId === $this->fromBookId) {
            $current = CurrentBook::id();
            $this->toBookId = (string) ($current !== (int) $this->fromBookId
                ? $current
                : ($books->firstWhere(fn (Book $b) => $b->id !== (int) $this->fromBookId)?->id ?? ''));
        }

        $this->date = now()->toDateString();
        $this->pickDefaults();
    }

    public function updatedFromBookId(): void
    {
        $this->fromCash = $this->fromCounter = '';
        $this->pickDefaults();
    }

    public function updatedToBookId(): void
    {
        $this->toCash = $this->toCounter = '';
        $this->pickDefaults();
    }

    public function save(BookTransferService $transfers): void
    {
        $this->validate([
            'fromBookId' => ['required', 'integer', 'different:toBookId'],
            'toBookId' => ['required', 'integer'],
            'fromCash' => ['required', 'integer'],
            'toCash' => ['required', 'integer'],
            'fromCounter' => ['nullable', 'integer'],
            'toCounter' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:300'],
        ], [
            'fromBookId.different' => 'Elegí dos contabilidades distintas.',
        ], [
            'fromCash' => 'cuenta de salida', 'toCash' => 'cuenta de entrada', 'amount' => 'monto',
            'date' => 'fecha', 'description' => 'descripción',
        ]);

        $transfer = $transfers->transfer($this->user(), [
            'from' => $this->book((int) $this->fromBookId),
            'from_cash' => (int) $this->fromCash,
            'from_counter' => $this->fromCounter !== '' ? (int) $this->fromCounter : null,
            'to' => $this->book((int) $this->toBookId),
            'to_cash' => (int) $this->toCash,
            'to_counter' => $this->toCounter !== '' ? (int) $this->toCounter : null,
            'amount' => $this->amount,
            'date' => $this->date,
            'description' => $this->description,
        ]);

        session()->flash('status', sprintf(
            'Transferencia registrada: asiento #%d en «%s» y #%d en «%s».',
            $transfer->fromEntry->number, $transfer->fromBook->label(),
            $transfer->toEntry->number, $transfer->toBook->label(),
        ));
        $this->reset('amount');
    }

    public function render()
    {
        $books = $this->books();
        $from = $books->firstWhere('id', (int) $this->fromBookId);
        $to = $books->firstWhere('id', (int) $this->toBookId);

        return view('livewire.books.transfer-between-books', [
            'books' => $books,
            'from' => $from,
            'to' => $to,
            'fromCashAccounts' => $from ? $this->cashAccounts($from) : collect(),
            'toCashAccounts' => $to ? $this->cashAccounts($to) : collect(),
            'fromAccounts' => $from ? $this->postable($from) : collect(),
            'toAccounts' => $to ? $this->postable($to) : collect(),
        ]);
    }

    private function pickDefaults(): void
    {
        $books = $this->books();

        if ($this->fromCash === '' && ($from = $books->firstWhere('id', (int) $this->fromBookId))) {
            $this->fromCash = (string) ($this->cashAccounts($from)->first()?->id ?? '');
        }
        if ($this->toCash === '' && ($to = $books->firstWhere('id', (int) $this->toBookId))) {
            $this->toCash = (string) ($this->cashAccounts($to)->first()?->id ?? '');
        }
    }

    /** @return Collection<int, Book> */
    private function books(): Collection
    {
        return $this->user()->books()
            ->orderByRaw("case when kind = 'personal' then 0 else 1 end")
            ->orderBy('name')
            ->get();
    }

    /** Cuentas de efectivo imputables (caja, bancos, Binance…) de una contabilidad. */
    private function cashAccounts(Book $book): Collection
    {
        $all = Account::withoutGlobalScopes()->where('book_id', $book->id)->orderBy('code')->get();
        $byId = $all->keyBy('id');

        return $all->filter(fn (Account $a): bool => $a->is_postable && $a->is_active
            && $a->type === AccountType::Asset
            && $a->effectiveCashFlowSection($byId) === CashFlowSection::Cash)->values();
    }

    private function postable(Book $book): Collection
    {
        return Account::withoutGlobalScopes()->where('book_id', $book->id)
            ->where('is_postable', true)->where('is_active', true)->orderBy('code')->get();
    }

    private function book(int $id): Book
    {
        return $this->user()->books()->whereKey($id)->firstOrFail();
    }

    private function user(): User
    {
        return auth()->user();
    }
}
