<?php

namespace App\Providers;

use App\Books\BookService;
use App\Mcp\PersonalTokens;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        // Al eliminar la cuenta de usuario se borran sus contabilidades propias
        // (la personal y las compartidas donde queda solo); en las compartidas
        // con otros miembros la propiedad pasa a otro miembro.
        User::deleting(function (User $user): void {
            DB::transaction(function () use ($user): void {
                app(BookService::class)->handleUserDeletion($user);
                $user->mcpTokens()->delete();
                DB::table('oauth_refresh_tokens')
                    ->whereIn('access_token_id', $user->tokens()->select('id'))
                    ->delete();
                $user->tokens()->delete();
                DB::table('oauth_auth_codes')->where('user_id', $user->id)->delete();
            });
        });

        // Quien se registra con un correo invitado se suma a esas contabilidades.
        Event::listen(Registered::class, fn (Registered $event) => $event->user instanceof User
            ? app(BookService::class)->acceptInvitations($event->user)
            : null);

        // MCP: tokens personales (Bearer «id|secreto») y OAuth para claude.ai.
        Auth::viaRequest('mcp-token', fn (Request $request) => app(PersonalTokens::class)->userFromRequest($request));

        Passport::authorizationView('mcp.authorize');
        Passport::tokensExpireIn(CarbonInterval::days(7));
        Passport::refreshTokensExpireIn(CarbonInterval::days(180));
    }
}
