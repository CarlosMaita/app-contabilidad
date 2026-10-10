<?php

use App\Demo\DemoSandbox;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:purge', function (DemoSandbox $sandbox) {
    $this->info('Cuentas demo borradas: '.$sandbox->purgeExpired());
})->purpose('Borra las cuentas demo vencidas');

Schedule::command('demo:purge')->hourly();
