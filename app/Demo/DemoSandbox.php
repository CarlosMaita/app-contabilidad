<?php

namespace App\Demo;

use App\Models\User;
use App\Modules\Accounting\Services\DefaultChartOfAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cuentas demo descartables: cada visitante que entra con «Ver como demo»
 * recibe su propio usuario con datos de ejemplo, que se borra al salir o
 * pasadas las horas de vida.
 */
class DemoSandbox
{
    public const LIFETIME_HOURS = 24;

    public function __construct(
        private readonly DemoData $data,
    ) {}

    public function create(?string $email = null, ?string $password = null): User
    {
        return DB::transaction(function () use ($email, $password): User {
            $user = new User([
                'name' => 'Invitado demo',
                'email' => $email ?? 'demo-'.Str::lower(Str::random(12)).'@demo.invalid',
                'password' => $password ?? Str::random(40),
            ]);
            $user->forceFill(['is_demo' => true, 'email_verified_at' => now()])->save();

            DefaultChartOfAccounts::seedFor($user->personalBook());
            $this->data->populate($user);

            return $user;
        });
    }

    /** Borra las cuentas demo vencidas (el hook de borrado purga sus datos). */
    public function purgeExpired(): int
    {
        $expired = User::where('is_demo', true)
            ->where('created_at', '<', now()->subHours(self::LIFETIME_HOURS))
            ->get();

        $expired->each->delete();

        return $expired->count();
    }
}
