<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\PnlSection;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Plan de cuentas')]
class ChartOfAccounts extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $type = AccountType::Asset->value;

    public string $pnl_section = '';

    public ?string $parent_id = null;

    public bool $is_postable = true;

    public bool $is_active = true;

    public string $search = '';

    public function create(?int $parentId = null): void
    {
        $this->resetForm();

        if ($parentId !== null) {
            $parent = Account::findOrFail($parentId);
            $this->parent_id = (string) $parent->id;
            $this->type = $parent->type->value;
            $this->pnl_section = $parent->effectivePnlSection()?->value ?? '';
            $this->code = $parent->code.'.';
        }

        $this->showForm = true;
    }

    public function updatedType(string $value): void
    {
        $this->pnl_section = PnlSection::defaultFor(AccountType::from($value))?->value ?? '';
    }

    public function edit(int $id): void
    {
        $account = Account::findOrFail($id);

        $this->resetForm();
        $this->editingId = $account->id;
        $this->code = $account->code;
        $this->name = $account->name;
        $this->type = $account->type->value;
        $this->pnl_section = $account->effectivePnlSection()?->value ?? '';
        $this->parent_id = $account->parent_id !== null ? (string) $account->parent_id : null;
        $this->is_postable = $account->is_postable;
        $this->is_active = $account->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[0-9]+(\.[0-9]+)*$/',
                Rule::unique('accounts', 'code')
                    ->where('user_id', auth()->id())
                    ->ignore($this->editingId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'pnl_section' => [
                'nullable',
                Rule::in(array_map(fn (PnlSection $s) => $s->value, PnlSection::forType(AccountType::from($this->type)))),
            ],
            'parent_id' => ['nullable', 'integer'],
            'is_postable' => ['boolean'],
            'is_active' => ['boolean'],
        ], [
            'pnl_section.in' => 'La sección del P&L no corresponde al tipo de cuenta.',
        ], [
            'code' => 'código',
            'name' => 'nombre',
            'type' => 'tipo',
            'pnl_section' => 'sección del P&L',
            'parent_id' => 'cuenta padre',
        ]);

        $parent = null;
        if ($this->parent_id !== null && $this->parent_id !== '') {
            $parent = Account::findOrFail((int) $this->parent_id);

            if ($this->editingId !== null && $this->isSelfOrDescendant($parent->id, $this->editingId)) {
                $this->addError('parent_id', 'La cuenta padre no puede ser la misma cuenta ni una de sus sub-cuentas.');

                return;
            }

            // Una sub-cuenta hereda el tipo y la sección del P&L de su padre.
            $validated['type'] = $parent->type->value;
            $validated['pnl_section'] = $parent->effectivePnlSection()?->value;
        }

        $validated['parent_id'] = $parent?->id;

        $finalType = AccountType::from($validated['type']);
        if (! in_array($finalType, [AccountType::Income, AccountType::Expense], true)) {
            $validated['pnl_section'] = null;
        } elseif (empty($validated['pnl_section'])) {
            $validated['pnl_section'] = PnlSection::defaultFor($finalType)?->value;
        }

        if ($this->editingId !== null) {
            Account::findOrFail($this->editingId)->update($validated);
        } else {
            Account::create($validated);
        }

        // Solo las hojas reciben apuntes: el padre deja de ser imputable.
        $parent?->update(['is_postable' => false]);

        $this->showForm = false;
        $this->resetForm();
        session()->flash('status', 'Cuenta guardada.');
    }

    public function delete(int $id): void
    {
        $account = Account::findOrFail($id);

        if ($account->children()->exists()) {
            session()->flash('error', 'No se puede eliminar una cuenta con sub-cuentas.');

            return;
        }

        // TODO Fase 3: bloquear si la cuenta tiene apuntes en el diario.
        $account->delete();
        session()->flash('status', 'Cuenta eliminada.');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function render()
    {
        return view('accounting::livewire.chart-of-accounts', [
            'rows' => $this->rows(),
            'parentOptions' => Account::orderBy('code')->get(),
            'types' => AccountType::cases(),
            'pnlOptions' => PnlSection::forType(AccountType::from($this->type)),
        ]);
    }

    /**
     * Cuentas aplanadas en orden de árbol con su profundidad.
     *
     * @return Collection<int, array{account: Account, depth: int}>
     */
    protected function rows(): Collection
    {
        $accounts = Account::orderBy('code')->get();

        if ($this->search !== '') {
            $term = mb_strtolower($this->search);

            return $accounts
                ->filter(fn (Account $a): bool => str_contains(mb_strtolower($a->name), $term)
                    || str_contains(mb_strtolower($a->code), $term))
                ->map(fn (Account $a): array => ['account' => $a, 'depth' => 0])
                ->values();
        }

        $byParent = $accounts->groupBy('parent_id');
        $rows = collect();

        $walk = function (?int $parentId, int $depth) use (&$walk, $byParent, $rows): void {
            foreach ($byParent->get($parentId, collect()) as $account) {
                $rows->push(['account' => $account, 'depth' => $depth]);
                $walk($account->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $rows;
    }

    protected function isSelfOrDescendant(int $candidateParentId, int $accountId): bool
    {
        $current = Account::find($candidateParentId);

        while ($current !== null) {
            if ($current->id === $accountId) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'name', 'parent_id']);
        $this->type = AccountType::Asset->value;
        $this->pnl_section = '';
        $this->is_postable = true;
        $this->is_active = true;
        $this->resetErrorBag();
    }
}
