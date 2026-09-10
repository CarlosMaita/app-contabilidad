<?php

namespace App\Modules\Operations\Livewire;

use App\Modules\Operations\Actions\ExecuteOperation;
use App\Modules\Operations\Enums\VariableType;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Shared\Contracts\ChartOfAccountsProvider;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ejecutar operación')]
class ExecuteOperationForm extends Component
{
    public OperationType $operationType;

    /** @var array<string, mixed> */
    public array $values = [];

    public string $executed_at = '';

    public string $description = '';

    public function mount(OperationType $operationType): void
    {
        abort_unless($operationType->is_active, 404);

        $this->operationType = $operationType->load('variables');
        $this->executed_at = now()->toDateString();

        foreach ($this->operationType->variables as $variable) {
            $this->values[$variable->name] = match ($variable->type) {
                VariableType::Boolean => filter_var($variable->default_value, FILTER_VALIDATE_BOOL),
                default => $variable->default_value ?? '',
            };
        }
    }

    public function save(ExecuteOperation $action): void
    {
        // Valida con las claves del componente para que los errores lleguen a cada input.
        $prefixedRules = collect($action->payloadRules($this->operationType))
            ->mapWithKeys(fn (array $rules, string $name): array => ["values.$name" => $rules])
            ->all();

        $this->validate(
            ['executed_at' => ['required', 'date'], ...$prefixedRules],
            [],
            [
                'executed_at' => 'fecha contable',
                ...$this->operationType->variables
                    ->mapWithKeys(fn ($v): array => ["values.{$v->name}" => $v->label])
                    ->all(),
            ],
        );

        $payload = $this->normalizedValues();

        $action->execute(
            $this->operationType,
            $payload,
            $this->executed_at,
            $this->description !== '' ? $this->description : null,
        );

        session()->flash('status', "Operación «{$this->operationType->name}» registrada.");
        $this->redirectRoute('events.index', navigate: true);
    }

    public function render()
    {
        return view('operations::livewire.execute-operation-form', [
            'accounts' => app(ChartOfAccountsProvider::class)->postableAccounts(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizedValues(): array
    {
        $payload = [];

        foreach ($this->operationType->variables as $variable) {
            $raw = $this->values[$variable->name] ?? null;

            if ($raw === '' || $raw === null) {
                $payload[$variable->name] = null;

                continue;
            }

            $payload[$variable->name] = match ($variable->type) {
                VariableType::Decimal => (string) $raw,
                VariableType::Integer, VariableType::Account => (int) $raw,
                VariableType::Boolean => (bool) $raw,
                default => $raw,
            };
        }

        return $payload;
    }
}
