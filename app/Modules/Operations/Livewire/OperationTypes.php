<?php

namespace App\Modules\Operations\Livewire;

use App\Modules\Operations\Enums\VariableType;
use App\Modules\Operations\Models\OperationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Operaciones')]
class OperationTypes extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public bool $is_active = true;

    /** @var array<int, array{id: ?int, name: string, label: string, type: string, is_required: bool, default_value: string}> */
    public array $variables = [];

    public function create(): void
    {
        $this->resetForm();
        $this->addVariable();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $type = OperationType::with('variables')->findOrFail($id);

        $this->resetForm();
        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->code = $type->code;
        $this->description = $type->description ?? '';
        $this->is_active = $type->is_active;
        $this->variables = $type->variables->map(fn ($v): array => [
            'id' => $v->id,
            'name' => $v->name,
            'label' => $v->label,
            'type' => $v->type->value,
            'is_required' => $v->is_required,
            'default_value' => $v->default_value ?? '',
        ])->all();
        $this->showForm = true;
    }

    public function addVariable(): void
    {
        $this->variables[] = [
            'id' => null,
            'name' => '',
            'label' => '',
            'type' => VariableType::Decimal->value,
            'is_required' => true,
            'default_value' => '',
        ];
    }

    public function removeVariable(int $index): void
    {
        unset($this->variables[$index]);
        $this->variables = array_values($this->variables);
    }

    public function save(): void
    {
        if ($this->code === '' && $this->name !== '') {
            $this->code = Str::slug($this->name, '_');
        }

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-z0-9]+(_[a-z0-9]+)*$/',
                Rule::unique('operation_types', 'code')
                    ->where('user_id', auth()->id())
                    ->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'variables' => ['array'],
            'variables.*.name' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', 'distinct'],
            'variables.*.label' => ['required', 'string', 'max:120'],
            'variables.*.type' => ['required', Rule::enum(VariableType::class)],
            'variables.*.is_required' => ['boolean'],
            'variables.*.default_value' => ['nullable', 'string', 'max:255'],
        ], [
            'variables.*.name.regex' => 'El nombre debe estar en snake_case (ej: monto_total).',
            'variables.*.name.distinct' => 'Hay nombres de variable repetidos.',
            'code.regex' => 'El código debe estar en snake_case (ej: pago_proveedor).',
        ], [
            'name' => 'nombre',
            'code' => 'código',
            'variables.*.name' => 'nombre de variable',
            'variables.*.label' => 'etiqueta',
            'variables.*.type' => 'tipo',
        ]);

        DB::transaction(function (): void {
            $data = [
                'name' => $this->name,
                'code' => $this->code,
                'description' => $this->description !== '' ? $this->description : null,
                'is_active' => $this->is_active,
            ];

            if ($this->editingId !== null) {
                $type = OperationType::findOrFail($this->editingId);
                $type->update($data);
            } else {
                $type = OperationType::create($data);
            }

            $keptIds = collect($this->variables)->pluck('id')->filter()->all();
            $type->variables()->whereNotIn('id', $keptIds)->delete();

            foreach (array_values($this->variables) as $order => $row) {
                $attributes = [
                    'name' => $row['name'],
                    'label' => $row['label'],
                    'type' => $row['type'],
                    'is_required' => (bool) $row['is_required'],
                    'default_value' => $row['default_value'] !== '' ? $row['default_value'] : null,
                    'sort_order' => $order,
                ];

                if ($row['id'] !== null) {
                    $type->variables()->whereKey($row['id'])->update($attributes);
                } else {
                    $type->variables()->create($attributes);
                }
            }
        });

        $this->showForm = false;
        $this->resetForm();
        session()->flash('status', 'Operación guardada.');
    }

    public function delete(int $id): void
    {
        $type = OperationType::findOrFail($id);

        if ($type->executions()->exists()) {
            session()->flash('error', 'No se puede eliminar una operación con ejecuciones registradas. Desactivala en su lugar.');

            return;
        }

        $type->delete();
        session()->flash('status', 'Operación eliminada.');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function render()
    {
        return view('operations::livewire.operation-types', [
            'types' => OperationType::withCount(['variables', 'executions'])->orderBy('name')->get(),
            'variableTypes' => VariableType::cases(),
        ]);
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code', 'description', 'variables']);
        $this->is_active = true;
        $this->resetErrorBag();
    }
}
