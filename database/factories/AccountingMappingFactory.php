<?php

namespace Database\Factories;

use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Shared\Books\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingMapping>
 */
class AccountingMappingFactory extends Factory
{
    protected $model = AccountingMapping::class;

    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'operation_type_id' => OperationType::factory(),
            'version' => 1,
            'is_active' => true,
            'description_template' => null,
        ];
    }
}
