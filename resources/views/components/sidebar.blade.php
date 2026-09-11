@php
    $groups = [
        'General' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard'],
        ],
        'Operativo' => [
            ['label' => 'Tipos de operación', 'route' => 'operations.index', 'active' => 'operations.*'],
            ['label' => 'Log de eventos', 'route' => 'events.index', 'active' => 'events.*'],
        ],
        'Contable' => [
            ['label' => 'Plan de cuentas', 'route' => 'accounts.index', 'active' => 'accounts.*'],
            ['label' => 'Mapeo contable', 'route' => 'mappings.index', 'active' => 'mappings.*'],
            ['label' => 'Libro diario', 'route' => 'journal.index', 'active' => 'journal.*'],
            ['label' => 'Libro mayor', 'route' => 'ledger.index', 'active' => 'ledger.*'],
            ['label' => 'Libros auxiliares', 'route' => 'subledgers.index', 'active' => 'subledgers.*'],
            ['label' => 'Reportes', 'route' => 'reports.index', 'active' => 'reports.*'],
        ],
    ];
@endphp

<aside class="hidden border-r-2 border-ink md:flex md:flex-col">
    <div class="border-b-2 border-ink p-4">
        <a href="{{ route('dashboard') }}" wire:navigate class="block">
            <div class="text-[15px] font-extrabold">Contabilidad</div>
            <div class="text-[11px] uppercase tracking-[0.08em] text-neutral-600">por eventos</div>
        </a>
    </div>

    @foreach ($groups as $label => $items)
        <div class="border-b border-neutral-300 py-3">
            <div class="kicker px-4 pb-1.5">{{ $label }}</div>
            @foreach ($items as $item)
                @php($active = request()->routeIs($item['active']))
                <a href="{{ route($item['route']) }}" wire:navigate
                   class="block border-l-[3px] py-[7px] pl-[13px] pr-3 text-[13px] {{ $active ? 'border-accent bg-neutral-200 font-extrabold' : 'border-transparent hover:bg-neutral-200' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    @endforeach

    <div class="mt-auto flex items-center gap-2 border-t-2 border-ink p-4">
        <div class="flex h-7 w-7 items-center justify-center border border-neutral-400 text-[11px] font-extrabold">
            {{ mb_strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
        </div>
        <div class="min-w-0 text-xs leading-tight">
            <div class="truncate font-extrabold">{{ auth()->user()?->name }}</div>
            <div class="flex gap-2">
                <a href="{{ route('profile') }}" wire:navigate class="text-[11px] text-neutral-700 hover:text-accent">Perfil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-[11px] text-neutral-700 hover:text-accent">Salir</button>
                </form>
            </div>
        </div>
    </div>
</aside>

{{-- Navegación compacta para pantallas chicas --}}
<nav class="flex gap-1 overflow-x-auto border-b-2 border-ink px-2 py-2 md:hidden">
    @foreach ($groups as $items)
        @foreach ($items as $item)
            @php($active = request()->routeIs($item['active']))
            <a href="{{ route($item['route']) }}" wire:navigate
               class="whitespace-nowrap border px-2.5 py-1.5 text-xs {{ $active ? 'border-accent font-extrabold text-accent' : 'border-transparent text-ink' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    @endforeach
</nav>
