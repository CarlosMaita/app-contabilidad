<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\CashFlowSection;
use App\Modules\Accounting\Enums\PnlSection;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Alta y edición de cuentas del plan con sus reglas: código único por usuario,
 * herencia de tipo y secciones desde el padre, auxiliares solo bajo una
 * principal y el padre deja de ser imputable al recibir hijas.
 * Lo usan el plan de cuentas (Livewire) y el servidor MCP.
 */
class AccountManager
{
    /**
     * @param  array<string, mixed>  $data  code, name, type, pnl_section, cash_flow_section,
     *                                      parent_id, is_postable, is_auxiliary, is_active
     *
     * @throws ValidationException
     */
    public function save(array $data, ?int $editingId = null): Account
    {
        $data += [
            'pnl_section' => null,
            'cash_flow_section' => null,
            'parent_id' => null,
            'is_postable' => true,
            'is_auxiliary' => false,
            'is_active' => true,
        ];

        // El tipo se valida primero: las reglas de secciones dependen de él.
        Validator::make($data, ['type' => ['required', Rule::enum(AccountType::class)]], [], ['type' => 'tipo'])->validate();
        $type = AccountType::from($data['type']);

        $validated = Validator::make($data, [
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[0-9]+(\.[0-9]+)*$/',
                Rule::unique('accounts', 'code')
                    ->where('user_id', Auth::id())
                    ->ignore($editingId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'pnl_section' => [
                'nullable',
                Rule::in(array_map(fn (PnlSection $s) => $s->value, PnlSection::forType($type))),
            ],
            'cash_flow_section' => [
                'nullable',
                Rule::in(array_map(fn (CashFlowSection $s) => $s->value, CashFlowSection::forType($type))),
            ],
            'parent_id' => ['nullable', 'integer'],
            'is_postable' => ['boolean'],
            'is_auxiliary' => ['boolean'],
            'is_active' => ['boolean'],
        ], [
            'pnl_section.in' => 'La sección del P&L no corresponde al tipo de cuenta.',
            'cash_flow_section.in' => 'La actividad del flujo de efectivo no corresponde al tipo de cuenta.',
        ], [
            'code' => 'código',
            'name' => 'nombre',
            'type' => 'tipo',
            'pnl_section' => 'sección del P&L',
            'parent_id' => 'cuenta padre',
        ])->validate();

        $parent = null;
        if ($validated['parent_id'] !== null && $validated['parent_id'] !== '') {
            $parent = Account::find((int) $validated['parent_id'])
                ?? throw ValidationException::withMessages(['parent_id' => 'La cuenta padre no existe.']);

            if ($editingId !== null && $this->isSelfOrDescendant($parent->id, $editingId)) {
                throw ValidationException::withMessages(['parent_id' => 'La cuenta padre no puede ser la misma cuenta ni una de sus sub-cuentas.']);
            }

            if ($parent->is_auxiliary) {
                throw ValidationException::withMessages(['parent_id' => 'Una cuenta auxiliar no puede tener sub-cuentas.']);
            }

            // Una sub-cuenta hereda el tipo y la sección del P&L de su padre.
            $validated['type'] = $parent->type->value;
            $validated['pnl_section'] = $parent->effectivePnlSection()?->value;
            $validated['cash_flow_section'] = $parent->effectiveCashFlowSection()?->value;
        }

        $validated['parent_id'] = $parent?->id;

        // Un auxiliar solo existe bajo una cuenta principal.
        if ($parent === null) {
            $validated['is_auxiliary'] = false;
        }

        $finalType = AccountType::from($validated['type']);
        if (! in_array($finalType, [AccountType::Income, AccountType::Expense], true)) {
            $validated['pnl_section'] = null;
        } elseif (empty($validated['pnl_section'])) {
            $validated['pnl_section'] = PnlSection::defaultFor($finalType)?->value;
        }

        if (CashFlowSection::forType($finalType) === []) {
            $validated['cash_flow_section'] = null;
        } elseif (empty($validated['cash_flow_section'])) {
            $validated['cash_flow_section'] = CashFlowSection::defaultFor($finalType)?->value;
        }

        if ($editingId !== null) {
            $account = Account::findOrFail($editingId);
            $account->update($validated);
        } else {
            $account = Account::create($validated);
        }

        // Solo las hojas reciben apuntes: el padre deja de ser imputable.
        $parent?->update(['is_postable' => false]);

        return $account;
    }

    private function isSelfOrDescendant(int $candidateParentId, int $accountId): bool
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
}
