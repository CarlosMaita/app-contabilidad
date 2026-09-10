<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\Account;
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
            'user_id' => User::factory(),
            'code' => fake()->unique()->numerify('#.#.##'),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(AccountType::cases()),
            'parent_id' => null,
            'is_postable' => true,
            'is_active' => true,
        ];
    }
}
