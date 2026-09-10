<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Operations\Models\OperationType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OperationType>
 */
class OperationTypeFactory extends Factory
{
    protected $model = OperationType::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'user_id' => User::factory(),
            'name' => ucfirst($name),
            'code' => Str::slug($name, '_'),
            'description' => null,
            'is_active' => true,
        ];
    }
}
