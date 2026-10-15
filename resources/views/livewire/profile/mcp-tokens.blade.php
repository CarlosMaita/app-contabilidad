<?php

use App\Mcp\PersonalTokens;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    /** Token recién creado: se muestra una sola vez. */
    public ?string $plainToken = null;

    public function createToken(PersonalTokens $tokens): void
    {
        abort_if(Auth::user()->is_demo, 403);

        $this->validate(['name' => ['required', 'string', 'max:60']], [], ['name' => 'nombre']);

        $this->plainToken = $tokens->create(Auth::user(), $this->name);
        $this->reset('name');
    }

    public function revoke(int $id): void
    {
        Auth::user()->mcpTokens()->whereKey($id)->delete();
        $this->plainToken = null;
    }

    /** Desconecta una aplicación OAuth: revoca todos sus tokens de este usuario. */
    public function disconnect(string $clientId): void
    {
        $tokens = Auth::user()->tokens()->where('client_id', $clientId);

        DB::table('oauth_refresh_tokens')->whereIn('access_token_id', (clone $tokens)->select('id'))->update(['revoked' => true]);
        $tokens->update(['revoked' => true]);
    }

    public function with(): array
    {
        $apps = Auth::user()->tokens()
            ->with('client')
            ->where('revoked', false)
            ->get()
            ->groupBy('client_id')
            ->map(fn ($tokens) => [
                'client_id' => $tokens->first()->client_id,
                'name' => $tokens->first()->client?->name ?? 'Aplicación',
                'since' => $tokens->min('created_at'),
                'last' => $tokens->max('created_at'),
            ])
            ->values();

        return [
            'tokens' => Auth::user()->mcpTokens()->latest()->get(),
            'apps' => $apps,
            'endpoint' => url('/mcp'),
        ];
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Acceso MCP (Claude)</h2>
        <p class="mt-1 text-sm text-gray-600">
            Gestioná la contabilidad de esta cuenta desde Claude. Quien tenga acceso puede consultar y registrar asientos.
        </p>
    </header>

    <div class="mt-4 border border-neutral-300 bg-neutral-100 p-3 text-sm">
        <p class="font-extrabold">claude.ai (web, escritorio o celular)</p>
        <p class="mt-1 text-neutral-700">
            En Claude: Configuración → Conectores → «Añadir conector personalizado», pegá
            <span class="break-all font-mono text-xs">{{ $endpoint }}</span> y autorizá con tu usuario. No necesita token.
        </p>
    </div>

    @if ($apps->isNotEmpty())
        <div class="mt-4">
            <h3 class="text-sm font-extrabold">Aplicaciones conectadas</h3>
            <ul class="mt-2 divide-y divide-neutral-200 border border-neutral-300">
                @foreach ($apps as $app)
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm" wire:key="app-{{ $app['client_id'] }}">
                        <span class="grow font-extrabold">{{ $app['name'] }}</span>
                        <span class="text-xs text-neutral-600">
                            conectada {{ $app['since']->format('d/m/Y') }} · última sesión {{ $app['last']->diffForHumans() }}
                        </span>
                        <button type="button" class="btn-ghost text-xs" wire:click="disconnect('{{ $app['client_id'] }}')"
                                wire:confirm="¿Desconectar «{{ $app['name'] }}»? Tendrá que volver a autorizarse.">
                            Desconectar
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6">
        <p class="text-sm font-extrabold">Claude Code / Claude Desktop: token personal</p>
        <form wire:submit="createToken" class="mt-2 flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] grow">
                <x-input-label for="mcp_token_name" value="Nombre del token" />
                <x-text-input wire:model="name" id="mcp_token_name" class="mt-1 block w-full" placeholder="Claude Code en mi notebook" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <x-primary-button>Generar token</x-primary-button>
        </form>
    </div>

    @if ($plainToken)
        <div class="note-accent mt-4 text-sm" x-data="{ copied: false }">
            <p class="font-extrabold">Copiá el token ahora: no se vuelve a mostrar.</p>
            <code class="mt-2 block break-all bg-white px-2 py-1.5 font-mono text-xs">{{ $plainToken }}</code>
            <p class="mt-3">Para registrarlo en Claude Code, ejecutá:</p>
            <code class="mt-1 block break-all bg-white px-2 py-1.5 font-mono text-xs" x-ref="cmd">claude mcp add --transport http contable {{ $endpoint }} --header "Authorization: Bearer {{ $plainToken }}"</code>
            <button type="button" class="btn-secondary mt-2 px-2.5 py-1.5 text-xs"
                    x-on:click="navigator.clipboard.writeText($refs.cmd.innerText); copied = true">
                <span x-show="! copied">Copiar comando</span>
                <span x-show="copied" x-cloak>Copiado</span>
            </button>
        </div>
    @endif

    <div class="mt-4">
        <h3 class="text-sm font-extrabold">Tokens personales</h3>
        <ul class="mt-2 divide-y divide-neutral-200 border border-neutral-300">
            @forelse ($tokens as $token)
                <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm" wire:key="token-{{ $token->id }}">
                    <span class="grow font-extrabold">{{ $token->name }}</span>
                    <span class="text-xs text-neutral-600">
                        creado {{ $token->created_at->format('d/m/Y') }} ·
                        {{ $token->last_used_at ? 'último uso '.$token->last_used_at->diffForHumans() : 'sin uso' }}
                    </span>
                    <button type="button" class="btn-ghost text-xs" wire:click="revoke({{ $token->id }})"
                            wire:confirm="¿Revocar el token «{{ $token->name }}»? Claude dejará de tener acceso con él.">
                        Revocar
                    </button>
                </li>
            @empty
                <li class="px-3 py-3 text-sm text-neutral-600">No hay tokens.</li>
            @endforelse
        </ul>
    </div>
</section>
