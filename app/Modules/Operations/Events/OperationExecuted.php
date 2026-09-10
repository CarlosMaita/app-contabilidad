<?php

namespace App\Modules\Operations\Events;

use App\Modules\Operations\Models\OperationExecution;
use Illuminate\Foundation\Events\Dispatchable;

class OperationExecuted
{
    use Dispatchable;

    public function __construct(
        public readonly OperationExecution $execution,
    ) {}
}
