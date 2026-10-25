<?php

namespace Database\Factories;

use App\Modules\Operations\Models\OperationType;
use App\Modules\Shared\Books\Book;
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
            'book_id' => Book::factory(),
            'name' => ucfirst($name),
            'code' => Str::slug($name, '_'),
            'description' => null,
            'is_active' => true,
        ];
    }
}
