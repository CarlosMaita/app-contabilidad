<?php

namespace App\Providers;

use App\Mcp\PersonalTokens;
use App\Models\User;
use App\Modules\Accounting\Services\PurgeAccountingData;
use App\Modules\Operations\Services\PurgeOperationsData;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Al eliminar la cuenta de usuario se purgan sus datos de negocio
        // en orden seguro de FKs: primero lo contable (los asientos
        // referencian ejecuciones y mapeos), después lo operativo.
        User::deleting(function (User $user): void {
            DB::transaction(function () use ($user): void {
                PurgeAccountingData::forUser($user);
                PurgeOperationsData::forUser($user);
                $user->mcpTokens()->delete();
                DB::table('oauth_refresh_tokens')
                    ->whereIn('access_token_id', $user->tokens()->select('id'))
                    ->delete();
                $user->tokens()->delete();
                DB::table('oauth_auth_codes')->where('user_id', $user->id)->delete();
            });
        });

        // MCP: tokens personales (Bearer «id|secreto») y OAuth para claude.ai.
        Auth::viaRequest('mcp-token', fn (Request $request) => app(PersonalTokens::class)->userFromRequest($request));

        Passport::authorizationView('mcp.authorize');
        Passport::tokensExpireIn(CarbonInterval::days(7));
        Passport::refreshTokensExpireIn(CarbonInterval::days(180));
    }
}
