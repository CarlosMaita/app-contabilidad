<?php

use App\Models\User;

test('el manifest cumple los requisitos de instalación de la PWA', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->not->toBeEmpty()
        ->and($manifest['short_name'])->toBe('Contable')
        ->and($manifest['start_url'])->toBe('/dashboard')
        ->and($manifest['display'])->toBe('standalone');

    $icons = collect($manifest['icons']);

    foreach (['192x192', '512x512'] as $size) {
        expect($icons->where('sizes', $size)->where('purpose', 'any'))->not->toBeEmpty();
    }
    expect($icons->where('purpose', 'maskable'))->not->toBeEmpty();

    // Cada ícono existe y mide lo que declara.
    foreach ($icons as $icon) {
        [$width, $height] = getimagesize(public_path(ltrim($icon['src'], '/')));
        expect("{$width}x{$height}")->toBe($icon['sizes']);
    }
});

test('el service worker y la página sin conexión existen', function () {
    expect(public_path('sw.js'))->toBeFile()
        ->and(file_get_contents(public_path('sw.js')))->toContain('/offline.html')
        ->and(public_path('offline.html'))->toBeFile()
        ->and(public_path('icons/apple-touch-icon.png'))->toBeFile();
});

test('las páginas enlazan el manifest y el ícono para iPhone', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.json">', false)
        ->assertSee('apple-touch-icon', false);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.json">', false)
        ->assertSee('Instalá la app en tu teléfono');
});
