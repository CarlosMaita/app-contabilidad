<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Livewire\Volt\Volt;

const CLAUDE_CALLBACK = 'https://claude.ai/api/mcp/auth_callback';

/**
 * Recorre el flujo OAuth que hace el conector de claude.ai: registro
 * dinámico del cliente, autorización del usuario (PKCE) y canje del código.
 *
 * @return array{client_id: string, access_token: string, refresh_token: string}
 */
function connectClaude(User $user): array
{
    $client = test()->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => [CLAUDE_CALLBACK],
    ])->assertCreated()->json();

    $verifier = str_repeat('v', 64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    test()->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client['client_id'],
        'redirect_uri' => CLAUDE_CALLBACK,
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'estado-123',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertOk()->assertSee('Autorizar a Claude');

    $redirect = test()->post('/oauth/authorize', [
        'client_id' => $client['client_id'],
        'auth_token' => session('authToken'),
    ])->assertRedirect()->headers->get('Location');

    expect($redirect)->toStartWith(CLAUDE_CALLBACK);
    parse_str(parse_url($redirect, PHP_URL_QUERY), $query);
    expect($query['state'])->toBe('estado-123');

    $tokens = test()->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client['client_id'],
        'redirect_uri' => CLAUDE_CALLBACK,
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->assertOk()->json();

    // Las llamadas siguientes no deben depender de la sesión web.
    auth()->forgetGuards();

    return ['client_id' => $client['client_id'], ...$tokens];
}

function mcpWith(string $token, string $tool = 'resumen_cuenta'): TestResponse
{
    return test()->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json, text/event-stream',
    ])->postJson('/mcp', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => $tool, 'arguments' => (object) []],
    ]);
}

test('publica los metadatos OAuth que descubre el conector', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"');

    $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()
        ->assertJsonPath('resource', url('/mcp'))
        ->assertJsonPath('authorization_servers.0', url('/'));

    $this->getJson('/.well-known/oauth-authorization-server')->assertOk()
        ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))
        ->assertJsonPath('token_endpoint', url('/oauth/token'))
        ->assertJsonPath('registration_endpoint', url('/oauth/register'));
});

test('solo Claude puede registrarse como cliente OAuth', function () {
    $this->postJson('/oauth/register', ['client_name' => 'Otro', 'redirect_uris' => ['https://evil.example/callback']])
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_redirect_uri');

    $this->postJson('/oauth/register', ['client_name' => 'Claude Code', 'redirect_uris' => ['http://localhost:33418/callback']])
        ->assertCreated();
});

test('el conector de claude.ai autoriza con OAuth y opera como el usuario', function () {
    $user = User::factory()->create(['name' => 'Usuaria OAuth']);

    $tokens = connectClaude($user);

    mcpWith($tokens['access_token'])->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.usuario', 'Usuaria OAuth');

    // El refresh token renueva el acceso sin volver a autorizar.
    $refreshed = $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $tokens['client_id'],
        'refresh_token' => $tokens['refresh_token'],
    ])->assertOk()->json('access_token');

    mcpWith($refreshed)->assertOk()->assertJsonPath('result.structuredContent.usuario', 'Usuaria OAuth');
});

test('desconectar la aplicación desde el Perfil corta el acceso', function () {
    $user = User::factory()->create();
    $tokens = connectClaude($user);

    $this->actingAs($user);
    Volt::test('profile.mcp-tokens')
        ->assertSee('Aplicaciones conectadas')
        ->assertSee('Claude')
        ->call('disconnect', $tokens['client_id']);
    auth()->forgetGuards();

    mcpWith($tokens['access_token'])->assertUnauthorized();
    $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $tokens['client_id'],
        'refresh_token' => $tokens['refresh_token'],
    ])->assertStatus(400);
});

test('una cuenta demo no puede usar el MCP aunque obtenga un token', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_demo' => true])->save();

    $tokens = connectClaude($user);

    mcpWith($tokens['access_token'])->assertForbidden();
});

test('al borrar el usuario se borran sus tokens OAuth', function () {
    $user = User::factory()->create();
    connectClaude($user);

    $user->delete();

    expect(DB::table('oauth_access_tokens')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('oauth_refresh_tokens')->count())->toBe(0);
});
