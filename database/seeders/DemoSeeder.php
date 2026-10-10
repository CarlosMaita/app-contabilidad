<?php

namespace Database\Seeders;

use App\Demo\DemoSandbox;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuario demo fijo con datos de ejemplo (demo@contable.test / demo1234).
 * Se recrea en cada corrida. En producción el botón «Ver como demo» del login
 * crea cuentas demo descartables por visitante, sin usar este seeder.
 */
class DemoSeeder extends Seeder
{
    public const EMAIL = 'demo@contable.test';

    public function run(DemoSandbox $sandbox): void
    {
        User::where('email', self::EMAIL)->get()->each->delete();

        $user = $sandbox->create(self::EMAIL, 'demo1234');
        $user->forceFill(['name' => 'Demo', 'is_demo' => false])->save();

        $this->command?->info('Usuario demo: '.self::EMAIL.' / demo1234');
    }
}
