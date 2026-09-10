<?php

use App\Modules\Accounting\AccountingServiceProvider;
use App\Modules\Operations\OperationsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    VoltServiceProvider::class,
    OperationsServiceProvider::class,
    AccountingServiceProvider::class,
];
