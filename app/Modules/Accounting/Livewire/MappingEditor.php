<?php

namespace App\Modules\Accounting\Livewire;

use App\Modules\Accounting\Exceptions\MappingResolutionException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Accounting\Services\ExpressionEvaluator;
use App\Modules\Accounting\Services\ProcessUnmappedExecutions;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Enums\VariableType;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Shared\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mapeo contable')]
class MappingEditor extends Component
{
    public OperationType $operationType;

    public string $description_template = '';

    /** @var array<int, array{side: string, target: string, account_id: string, account_variable: string, amount_expression: string, memo_template: string}> */
    public array $lines = [];

    public ?int $activeVersion = null;

    public function mount(OperationType $operationType): void
    {
        $this->operationType = $operationType->load('variables');

        $active = AccountingMapping::with('lines')
            ->where('operation_type_id', $operationType->id)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        if ($active !== null) {
            $this->activeVersion = $active->version;
            $this->description_template = $active->description_template ?? '';
            $this->lines = $active->lines->map(fn ($l): array => [
                'side' => $l->side->value,
                'target' => $l->account_variable !== null ? 'variable' : 'fixed',
                'account_id' => $l->account_id !== null ? (string) $l->account_id : '',
                'account_variable' => $l->account_variable ?? '',
                'amount_expression' => $l->amount_expression,
                'memo_template' => $l->memo_template ?? '',
            ])->all();
        } else {
            $this->addLine('debit');
            $this->addLine('credit');
        }
    }

    public function addLine(string $side): void
    {
        $this->lines[] = [
            'side' => $side === 'credit' ? 'credit' : 'debit',
            'target' => 'fixed',
            'account_id' => '',
            'account_variable' => '',
            'amount_expression' => '',
            'memo_template' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(): void
    {
        // Cada target usa solo su campo; el otro vuelve a vacío para no
        // arrastrar valores viejos ni fallar validaciones invisibles.
        foreach ($this->lines as $i => $line) {
            if ($line['target'] === 'variable') {
                $this->lines[$i]['account_id'] = null;
            } else {
                $this->lines[$i]['account_variable'] = null;
            }
        }

        $postableIds = Account::postable()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $accountVariables = $this->accountVariables()->pluck('name')->all();

        $this->validate([
            'description_template' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.side' => ['required', Rule::in(['debit', 'credit'])],
            'lines.*.target' => ['required', Rule::in(['fixed', 'variable'])],
            'lines.*.account_id' => ['required_if:lines.*.target,fixed', 'nullable', Rule::in($postableIds)],
            'lines.*.account_variable' => ['required_if:lines.*.target,variable', 'nullable', Rule::in($accountVariables)],
            'lines.*.amount_expression' => ['required', 'string', 'max:255'],
            'lines.*.memo_template' => ['nullable', 'string', 'max:255'],
        ], [
            'lines.*.account_id.required_if' => 'Elegí una cuenta.',
            'lines.*.account_id.in' => 'La cuenta debe ser imputable y activa.',
            'lines.*.account_variable.required_if' => 'Elegí la variable de cuenta.',
            'lines.*.amount_expression.required' => 'Escribí la expresión del monto.',
        ]);

        $sides = collect($this->lines)->pluck('side');
        if (! $sides->contains('debit') || ! $sides->contains('credit')) {
            $this->addError('lines', 'El mapeo necesita al menos una línea al Debe y una al Haber.');

            return;
        }

        // Las expresiones deben evaluar con el payload de ejemplo.
        $evaluator = app(ExpressionEvaluator::class);
        $sample = $this->samplePayload();
        foreach ($this->lines as $i => $line) {
            try {
                $evaluator->evaluate($line['amount_expression'], $sample);
            } catch (MappingResolutionException $e) {
                $this->addError("lines.$i.amount_expression", $e->getMessage());

                return;
            }
        }

        DB::transaction(function (): void {
            AccountingMapping::where('operation_type_id', $this->operationType->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $version = (int) AccountingMapping::withoutGlobalScopes()
                ->where('operation_type_id', $this->operationType->id)
                ->max('version') + 1;

            $mapping = AccountingMapping::create([
                'operation_type_id' => $this->operationType->id,
                'version' => $version,
                'is_active' => true,
                'description_template' => $this->description_template !== '' ? $this->description_template : null,
            ]);

            foreach (array_values($this->lines) as $order => $line) {
                $mapping->lines()->create([
                    'side' => $line['side'],
                    'account_id' => $line['target'] === 'fixed' ? (int) $line['account_id'] : null,
                    'account_variable' => $line['target'] === 'variable' ? $line['account_variable'] : null,
                    'amount_expression' => trim($line['amount_expression']),
                    'memo_template' => $line['memo_template'] !== '' ? $line['memo_template'] : null,
                    'sort_order' => $order,
                ]);
            }

            $this->activeVersion = $version;
        });

        session()->flash('status', "Mapeo guardado (versión {$this->activeVersion}).");
    }

    public function processPending(): void
    {
        if ($this->activeVersion === null) {
            session()->flash('error', 'Primero guardá un mapeo.');

            return;
        }

        $result = app(ProcessUnmappedExecutions::class)
            ->process(auth()->id(), $this->operationType->id);

        session()->flash(
            'status',
            "Procesado: {$result['posted']} asiento(s) generado(s), {$result['failed']} con error (ver log de eventos).",
        );
    }

    public function render()
    {
        return view('accounting::livewire.mapping-editor', [
            'accounts' => Account::postable()->orderBy('code')->get(),
            'accountVariables' => $this->accountVariables(),
            'preview' => $this->preview(),
            'pendingCount' => $this->operationType->executions()
                ->whereIn('status', [ExecutionStatus::Pending, ExecutionStatus::Unmapped, ExecutionStatus::Failed])
                ->count(),
            'allowedFunctions' => ExpressionEvaluator::allowedFunctions(),
        ]);
    }

    /**
     * Previsualización con el payload de ejemplo.
     *
     * @return array{sample: array<string, mixed>, rows: array<int, array{side: string, account: string, amount: ?string, error: ?string}>, debit: string, credit: string, balanced: bool}
     */
    protected function preview(): array
    {
        $evaluator = app(ExpressionEvaluator::class);
        $sample = $this->samplePayload();
        $accounts = Account::orderBy('code')->get()->keyBy('id');

        $rows = [];
        $debit = '0.00';
        $credit = '0.00';

        foreach ($this->lines as $line) {
            $accountLabel = '—';
            if ($line['target'] === 'fixed' && $line['account_id'] !== '') {
                $account = $accounts->get((int) $line['account_id']);
                $accountLabel = $account !== null ? "{$account->code} {$account->name}" : '—';
            } elseif ($line['target'] === 'variable' && $line['account_variable'] !== '') {
                $accountLabel = '{'.$line['account_variable'].'}';
            }

            $amount = null;
            $error = null;

            if (trim($line['amount_expression']) !== '') {
                try {
                    $amount = $evaluator->evaluate($line['amount_expression'], $sample);

                    if ($line['side'] === 'debit') {
                        $debit = Money::add($debit, $amount);
                    } else {
                        $credit = Money::add($credit, $amount);
                    }
                } catch (MappingResolutionException $e) {
                    $error = $e->getMessage();
                }
            }

            $rows[] = [
                'side' => $line['side'],
                'account' => $accountLabel,
                'amount' => $amount,
                'error' => $error,
            ];
        }

        return [
            'sample' => $sample,
            'rows' => $rows,
            'debit' => $debit,
            'credit' => $credit,
            'balanced' => Money::equals($debit, $credit) && ! Money::isZero($debit),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function samplePayload(): array
    {
        $firstAccountId = Account::postable()->orderBy('code')->value('id') ?? 1;
        $sample = [];

        foreach ($this->operationType->variables as $variable) {
            $sample[$variable->name] = match ($variable->type) {
                VariableType::Decimal => 100.0,
                VariableType::Integer => 2,
                VariableType::Boolean => true,
                VariableType::Date => now()->toDateString(),
                VariableType::Account => $firstAccountId,
                default => 'ejemplo',
            };
        }

        return $sample;
    }

    protected function accountVariables()
    {
        return $this->operationType->variables->where('type', VariableType::Account)->values();
    }
}
