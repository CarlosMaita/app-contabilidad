<?php

use App\Mcp\PersonalTokens;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Volt\Volt;

test('el perfil permite generar y revocar tokens MCP', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/profile')->assertOk()->assertSee('Acceso MCP');

    $component = Volt::test('profile.mcp-tokens')
        ->set('name', 'Claude Code')
        ->call('createToken')
        ->assertHasNoErrors()
        ->assertSee('claude mcp add --transport http contable');

    expect($user->mcpTokens()->count())->toBe(1)
        ->and($component->get('plainToken'))->toContain('|');

    $component->call('revoke', $user->mcpTokens()->first()->id);

    expect($user->mcpTokens()->count())->toBe(0);
});

test('una cuenta demo no puede generar tokens', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_demo' => true])->save();
    $this->actingAs($user);

    $this->get('/profile')->assertOk()->assertDontSee('Acceso MCP');

    Volt::test('profile.mcp-tokens')->set('name', 'x')->call('createToken')->assertForbidden();

    expect($user->mcpTokens()->count())->toBe(0);
});

test('al borrar el usuario se borran sus tokens', function () {
    $user = User::factory()->create();
    app(PersonalTokens::class)->create($user, 't');

    $user->delete();

    expect(PersonalAccessToken::count())->toBe(0);
});
