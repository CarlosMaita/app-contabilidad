<?php

namespace App\Mcp;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tokens personales para conectar Claude Code / Desktop al MCP con un header
 * «Authorization: Bearer <id>|<secreto>». Usa la tabla y el formato de
 * Sanctum, pero sin su trait en User (choca con el de Passport, que atiende
 * OAuth para claude.ai); el guard «mcp-token» los valida.
 */
class PersonalTokens
{
    public function create(User $user, string $name): string
    {
        $secret = Str::random(40);

        $token = new PersonalAccessToken([
            'name' => $name,
            'token' => hash('sha256', $secret),
            'abilities' => ['mcp'],
        ]);
        $token->tokenable()->associate($user)->save();

        return "{$token->getKey()}|{$secret}";
    }

    /** Resuelve el usuario dueño del token Bearer de la request, si es válido. */
    public function userFromRequest(Request $request): ?User
    {
        $plain = $request->bearerToken();

        if ($plain === null || ! str_contains($plain, '|')) {
            return null;
        }

        $token = PersonalAccessToken::findToken($plain);
        $user = $token?->tokenable;

        if (! $user instanceof User || ($token->expires_at !== null && $token->expires_at->isPast())) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $user;
    }
}
