<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Builder;
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
            ->when($this->from !== '', fn (Builder $q) => $q->whereDate('date', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $q) => $q->whereDate('date', '<=', $this->to))
            ->when(trim($this->search) !== '', function (Builder $q): void {
                $term = trim($this->search);
                $like = '%'.$term.'%';

                $q->where(function (Builder $q) use ($term, $like): void {
                    $q->where('description', 'like', $like)
                        ->orWhereHas('lines.account', fn (Builder $q) => $q
                            ->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like))
                        ->orWhereHas('execution.operationType', fn (Builder $q) => $q->where('name', 'like', $like));

                    if (ctype_digit($term)) {
                        $q->orWhere('number', (int) $term);
                    }
                });
            })
            ->orderByDesc('date')
            ->orderByDesc('number')
            ->paginate(20);

        return view('accounting::livewire.journal-book', [
            'entries' => $entries,
        ]);
    }
}
