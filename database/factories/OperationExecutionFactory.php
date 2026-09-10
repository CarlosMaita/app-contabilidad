<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Operations\Models\OperationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationExecution>
 */
class OperationExecutionFactory extends Factory
{
    protected $model = OperationExecution::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'operation_type_id' => OperationType::factory(),
            'payload' => ['monto' => '100.00'],
            'executed_at' => now()->toDateString(),
            'description' => null,
            'status' => ExecutionStatus::Pending,
            'error_message' => null,
        ];
    }
}
