<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Models\JournalEntry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

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

    public function render()
    {
        $entries = JournalEntry::with(['lines.account', 'execution.operationType', 'reverses'])
            ->filtered($this->search, $this->from, $this->to)
            ->orderByDesc('date')
            ->orderByDesc('number')
            ->paginate(20);

        return view('accounting::livewire.journal-book', [
            'entries' => $entries,
        ]);
    }
}
