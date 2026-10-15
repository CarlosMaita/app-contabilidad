<x-guest-layout>
    <div class="flex flex-col gap-4">
        <div>
            <div class="kicker">Conectar aplicación</div>
            <h1 class="mt-1 text-[24px] font-extrabold leading-tight tracking-tight">
                Autorizar a {{ $client->name }}
            </h1>
        </div>

        <div class="border-2 border-ink bg-white p-4 text-sm">
            <p>
                <span class="font-extrabold">{{ $client->name }}</span> podrá usar el servidor MCP de tu cuenta:
            </p>
            <ul class="mt-2 list-disc pl-5 text-neutral-800">
                <li>consultar plan de cuentas, saldos, libros y reportes;</li>
                <li>registrar asientos, ejecutar operaciones, revertir asientos y crear cuentas.</li>
            </ul>
            <p class="mt-3 text-xs text-neutral-600">
                Cuenta: <span class="font-extrabold text-ink">{{ $user->email }}</span>.
                Podés revocar el acceso cuando quieras desde tu Perfil.
            </p>
        </div>

        @if ($user->is_demo)
            <div class="note-accent text-sm">
                Las cuentas demo no pueden conectarse a aplicaciones externas. Creá una cuenta para usar el MCP.
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="btn-secondary w-full justify-center">Cancelar</button>
            </form>

            @unless ($user->is_demo)
                <form method="POST" action="{{ route('passport.authorizations.approve') }}"
                      x-data="{ sending: false }" x-on:submit="sending = true">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="btn-primary w-full justify-center" x-bind:disabled="sending">
                        <span x-show="! sending">Autorizar</span>
                        <span x-show="sending" x-cloak>Autorizando…</span>
                    </button>
                </form>
            @endunless
        </div>
    </div>
</x-guest-layout>
