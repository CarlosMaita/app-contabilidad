<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    /** Token recién creado: se muestra una sola vez. */
    public ?string $plainToken = null;

    public function createToken(): void
    {
        abort_if(Auth::user()->is_demo, 403);

        $this->validate(['name' => ['required', 'string', 'max:60']], [], ['name' => 'nombre']);

        $this->plainToken = Auth::user()->createToken($this->name, ['mcp'])->plainTextToken;
        $this->reset('name');
    }

    public function revoke(int $id): void
    {
        Auth::user()->tokens()->whereKey($id)->delete();
        $this->plainToken = null;
    }

    public function with(): array
    {
        return [
            'tokens' => Auth::user()->tokens()->latest()->get(),
            'endpoint' => url('/mcp'),
        ];
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Acceso MCP (Claude)</h2>
        <p class="mt-1 text-sm text-gray-600">
            Generá un token para gestionar la contabilidad de esta cuenta desde Claude Code o Claude Desktop.
            Quien tenga el token puede consultar y registrar asientos: no lo compartas y revocalo si ya no lo usás.
        </p>
    </header>

    <form wire:submit="createToken" class="mt-6 flex flex-wrap items-end gap-3">
        <div class="min-w-[220px] grow">
            <x-input-label for="mcp_token_name" value="Nombre del token" />
            <x-text-input wire:model="name" id="mcp_token_name" class="mt-1 block w-full" placeholder="Claude Code en mi notebook" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <x-primary-button>Generar token</x-primary-button>
    </form>

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

    <div class="mt-6">
        <h3 class="text-sm font-extrabold">Tokens activos</h3>
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
        <p class="mt-2 text-xs text-neutral-600">Endpoint: <span class="font-mono">{{ $endpoint }}</span></p>
    </div>
</section>
