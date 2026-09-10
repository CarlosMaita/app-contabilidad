<?php

namespace App\Modules\Operations\Actions;

use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Enums\VariableType;
use App\Modules\Operations\Events\OperationExecuted;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Shared\Contracts\ChartOfAccountsProvider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExecuteOperation
{
    public function __construct(
        private readonly ChartOfAccountsProvider $accounts,
    ) {}

    /**
     * Valida el payload contra las variables del tipo de operación, registra
     * la ejecución (log de eventos) y despacha OperationExecuted.
     *
     * @param  array<string, mixed>  $payload  valores indexados por nombre de variable
     *
     * @throws ValidationException
     */
    public function execute(
        OperationType $type,
        array $payload,
        string $executedAt,
        ?string $description = null,
    ): OperationExecution {
        $validated = Validator::make(
            ['executed_at' => $executedAt, ...$payload],
            ['executed_at' => ['required', 'date'], ...$this->payloadRules($type)],
            [],
            $this->attributeNames($type),
        )->validate();

        $executedAt = $validated['executed_at'];
        unset($validated['executed_at']);

        $execution = OperationExecution::create([
            'operation_type_id' => $type->id,
            'payload' => $validated,
            'executed_at' => $executedAt,
            'description' => $description,
            'status' => ExecutionStatus::Pending,
        ]);

        OperationExecuted::dispatch($execution);

        return $execution;
    }

    /**
     * Reglas de validación generadas a partir de las variables del tipo.
     *
     * @return array<string, array<int, mixed>>
     */
    public function payloadRules(OperationType $type): array
    {
        $rules = [];

        foreach ($type->variables as $variable) {
            $variableRules = [$variable->is_required ? 'required' : 'nullable'];
            $variableRules = array_merge($variableRules, $variable->type->rules());

            if ($variable->type === VariableType::Account) {
                $variableRules[] = Rule::in($this->accounts->postableAccounts()->pluck('id')->all());
            }

            $rules[$variable->name] = $variableRules;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(OperationType $type): array
    {
        return [
            'executed_at' => 'fecha contable',
            ...$type->variables->pluck('label', 'name')->all(),
        ];
    }
}
