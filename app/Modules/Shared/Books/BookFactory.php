<?php

namespace App\Modules\Shared\Books;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'kind' => Book::SHARED,
            'template' => 'business',
            'owner_id' => User::factory(),
        ];
    }

    public function configure(): static
    {
        // El dueño queda como miembro con rol owner.
        return $this->afterCreating(function (Book $book): void {
            $book->members()->syncWithoutDetaching([$book->owner_id => ['role' => Book::OWNER]]);
        });
    }
}
