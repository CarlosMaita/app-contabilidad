<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\CashFlowSection;
use App\Modules\Accounting\Enums\PnlSection;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Models\MappingLine;
use App\Modules\Accounting\Services\AccountManager;
use Illuminate\Support\Collection;
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

    public string $cash_flow_section = '';

    public ?string $parent_id = null;

    public bool $is_postable = true;

    public bool $is_auxiliary = false;

    public bool $is_active = true;

    public string $search = '';

    public function create(?int $parentId = null): void
    {
        $this->resetForm();

        if ($parentId !== null) {
            $parent = Account::findOrFail($parentId);

            if ($parent->is_auxiliary) {
                session()->flash('error', 'Una cuenta auxiliar no puede tener sub-cuentas.');

                return;
            }

            $this->parent_id = (string) $parent->id;
            $this->type = $parent->type->value;
            $this->pnl_section = $parent->effectivePnlSection()?->value ?? '';
            $this->cash_flow_section = $parent->effectiveCashFlowSection()?->value ?? '';
            $this->code = $parent->code.'.';
        }

        $this->showForm = true;
    }

    public function updatedType(string $value): void
    {
        $this->pnl_section = PnlSection::defaultFor(AccountType::from($value))?->value ?? '';
        $this->cash_flow_section = CashFlowSection::defaultFor(AccountType::from($value))?->value ?? '';
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
        $this->cash_flow_section = $account->effectiveCashFlowSection()?->value ?? '';
        $this->parent_id = $account->parent_id !== null ? (string) $account->parent_id : null;
        $this->is_postable = $account->is_postable;
        $this->is_auxiliary = $account->is_auxiliary;
        $this->is_active = $account->is_active;
        $this->showForm = true;
    }

    public function save(AccountManager $accounts): void
    {
        $accounts->save([
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'pnl_section' => $this->pnl_section,
            'cash_flow_section' => $this->cash_flow_section,
            'parent_id' => $this->parent_id,
            'is_postable' => $this->is_postable,
            'is_auxiliary' => $this->is_auxiliary,
            'is_active' => $this->is_active,
        ], $this->editingId);

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

        if (JournalLine::where('account_id', $account->id)->exists()) {
            session()->flash('error', 'La cuenta tiene apuntes en el diario y no se puede eliminar. Desactivala en su lugar.');

            return;
        }

        if (MappingLine::where('account_id', $account->id)->exists()) {
            session()->flash('error', 'La cuenta se usa en un mapeo contable y no se puede eliminar. Quitala del mapeo o desactivala.');

            return;
        }

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
        $all = Account::orderBy('code')->get();

        return view('accounting::livewire.chart-of-accounts', [
            'rows' => $this->rows(),
            'parentOptions' => $all,
            'byId' => $all->keyBy('id'),
            'types' => AccountType::cases(),
            'pnlOptions' => PnlSection::forType(AccountType::from($this->type)),
            'cashFlowOptions' => CashFlowSection::forType(AccountType::from($this->type)),
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

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'name', 'parent_id']);
        $this->type = AccountType::Asset->value;
        $this->pnl_section = '';
        $this->cash_flow_section = CashFlowSection::defaultFor(AccountType::Asset)->value;
        $this->is_auxiliary = false;
        $this->is_postable = true;
        $this->is_active = true;
        $this->resetErrorBag();
    }
}
