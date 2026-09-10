<?php

namespace Database\Factories;

use App\Modules\Operations\Enums\VariableType;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Operations\Models\OperationVariable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationVariable>
 */
class OperationVariableFactory extends Factory
{
    protected $model = OperationVariable::class;

    public function definition(): array
    {
        return [
            'operation_type_id' => OperationType::factory(),
            'name' => fake()->unique()->lexify('var_????'),
            'label' => ucfirst(fake()->word()),
            'type' => VariableType::Decimal,
            'is_required' => true,
            'default_value' => null,
            'sort_order' => 0,
        ];
    }
}
