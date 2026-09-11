<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Accounting\Models\MappingLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MappingLine>
 */
class MappingLineFactory extends Factory
{
    protected $model = MappingLine::class;

    public function definition(): array
    {
        return [
            'accounting_mapping_id' => AccountingMapping::factory(),
            'side' => EntrySide::Debit,
            'account_id' => Account::factory(),
            'account_variable' => null,
            'amount_expression' => 'monto',
            'memo_template' => null,
            'sort_order' => 0,
        ];
    }
}
