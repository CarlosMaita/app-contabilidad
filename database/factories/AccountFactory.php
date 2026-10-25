<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\Account;
use App\Modules\Shared\Books\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'code' => fake()->unique()->numerify('#.#.##'),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(AccountType::cases()),
            'parent_id' => null,
            'is_postable' => true,
            'is_auxiliary' => false,
            'is_active' => true,
        ];
    }
}
