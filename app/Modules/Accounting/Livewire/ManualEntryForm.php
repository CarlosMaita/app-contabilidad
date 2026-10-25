<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Exceptions\ClosedPeriodException;
use App\Modules\Accounting\Exceptions\MappingResolutionException;
use App\Modules\Accounting\Exceptions\UnbalancedEntryException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\JournalEntryBuilder;
use App\Modules\Shared\Books\CurrentBook;
use App\Modules\Shared\Money\Money;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Asiento manual')]
class ManualEntryForm extends Component
{
    public string $date = '';

    public string $description = '';

    /** @var array<int, array{side: string, account_id: string, amount: string, memo: string}> */
    public array $lines = [];

    /** Número del asiento usado como plantilla (?duplicate={id}); solo informativo. */
    public ?int $duplicatedFrom = null;

    public function mount(): void
    {
        $this->date = now()->toDateString();

        $source = request()->integer('duplicate')
            ? JournalEntry::with('lines')->find(request()->integer('duplicate'))
            : null;

        if ($source === null) {
            $this->addLine('debit');
            $this->addLine('credit');

            return;
        }

        // Pre-rellena con los datos del asiento original; no se registra nada
        // hasta que el usuario guarde. La fecha queda en hoy.
        $postableIds = Account::postable()->pluck('id')->all();

        $this->duplicatedFrom = $source->number;
        $this->description = $source->description;
        $this->lines = $source->lines->map(fn ($line): array => [
            'side' => $line->debit != 0 ? 'debit' : 'credit',
            'account_id' => in_array((int) $line->account_id, array_map('intval', $postableIds), true) ? (string) $line->account_id : '',
            'amount' => (string) ($line->debit != 0 ? $line->debit : $line->credit),
            'memo' => (string) $line->memo,
        ])->all();
    }

    public function addLine(string $side): void
    {
        $this->lines[] = [
            'side' => $side === 'credit' ? 'credit' : 'debit',
            'account_id' => '',
            'amount' => '',
            'memo' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(JournalEntryBuilder $builder): void
    {
        $postableIds = Account::postable()->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->validate([
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.side' => ['required', Rule::in(['debit', 'credit'])],
            'lines.*.account_id' => ['required', Rule::in($postableIds)],
            'lines.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ], [
            'lines.*.account_id.required' => 'Elegí una cuenta.',
            'lines.*.account_id.in' => 'La cuenta debe ser imputable y activa.',
            'lines.*.amount.required' => 'Ingresá el monto.',
            'lines.*.amount.gt' => 'El monto debe ser mayor a cero.',
        ], [
            'date' => 'fecha',
            'description' => 'descripción',
        ]);

        try {
            $entry = $builder->post(
                bookId: CurrentBook::id(),
                date: $this->date,
                description: $this->description,
                lines: array_map(fn (array $line): array => [
                    'side' => EntrySide::from($line['side']),
                    'account_id' => (int) $line['account_id'],
                    'amount' => Money::normalize($line['amount']),
                    'memo' => $line['memo'] !== '' ? $line['memo'] : null,
                ], $this->lines),
            );
        } catch (UnbalancedEntryException|MappingResolutionException|ClosedPeriodException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }

        session()->flash('status', "Asiento manual #{$entry->number} registrado.");
        $this->redirectRoute('journal.index', navigate: true);
    }

    public function render()
    {
        $debit = '0.00';
        $credit = '0.00';

        foreach ($this->lines as $line) {
            if (is_numeric($line['amount'])) {
                $amount = Money::normalize($line['amount']);
                $line['side'] === 'debit'
                    ? $debit = Money::add($debit, $amount)
                    : $credit = Money::add($credit, $amount);
            }
        }

        return view('accounting::livewire.manual-entry-form', [
            'accounts' => Account::postable()->orderBy('code')->get(),
            'totalDebit' => $debit,
            'totalCredit' => $credit,
            'balanced' => Money::equals($debit, $credit) && ! Money::isZero($debit),
        ]);
    }
}
