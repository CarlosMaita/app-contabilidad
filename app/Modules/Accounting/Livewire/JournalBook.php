<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Models\BookTransfer;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\ReverseJournalEntry;
use App\Modules\Shared\Books\CurrentBook;
use App\Modules\Shared\Contracts\VoidsOperationExecutions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('layouts.app')]
#[Title('Libro diario')]
class JournalBook extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public ?int $expandedId = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function reverse(int $entryId): void
    {
        $entry = JournalEntry::findOrFail($entryId);

        try {
            if ($entry->operation_execution_id !== null) {
                // Mantiene consistente el log de eventos: anula la ejecución.
                app(VoidsOperationExecutions::class)->voidExecution($entry->operation_execution_id);
                session()->flash('status', 'Operación anulada con contra-asiento.');
            } else {
                $reversal = app(ReverseJournalEntry::class)->reverse($entry);
                session()->flash('status', "Contra-asiento #{$reversal->number} registrado.");
            }
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Asientos que son parte de una transferencia => contabilidad del otro lado.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function transfersFor(array $ids): array
    {
        $other = [];

        foreach (BookTransfer::with(['fromBook', 'toBook'])->whereIn('from_entry_id', $ids)->orWhereIn('to_entry_id', $ids)->get() as $t) {
            $other[$t->from_entry_id] = $t->toBook->label();
            $other[$t->to_entry_id] = $t->fromBook->label();
        }

        return $other;
    }

    public function render()
    {
        $entries = JournalEntry::with(['lines.account', 'execution.operationType', 'reverses', 'author'])
            ->filtered($this->search, $this->from, $this->to)
            ->orderByDesc('date')
            ->orderByDesc('number')
            ->paginate(20);

        return view('accounting::livewire.journal-book', [
            'entries' => $entries,
            'shared' => ! CurrentBook::get()->isPersonal(),
            'transfers' => $this->transfersFor($entries->getCollection()->pluck('id')->all()),
        ]);
    }
}
