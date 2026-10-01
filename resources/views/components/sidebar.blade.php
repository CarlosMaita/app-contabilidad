@php
    $groups = [
        'General' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard'],
            ['label' => 'Saldos de cuentas', 'route' => 'balances.index', 'active' => 'balances.*'],
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

    <x-nav-links :groups="$groups" />

    <x-nav-user class="mt-auto" />
</aside>

{{-- Pantallas chicas: barra superior + panel lateral --}}
<div x-data="{ open: false }"
     x-effect="document.documentElement.classList.toggle('overflow-hidden', open)"
     x-on:keydown.escape.window="open = false"
     class="md:hidden">
    <div class="flex items-center gap-3 border-b-2 border-ink px-4 py-3">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-2.5">
            <img src="/icons/icon-192.png" alt="" class="h-7 w-7 shrink-0">
            <span class="truncate text-[15px] font-extrabold">Contabilidad</span>
        </a>
        <button type="button" x-on:click="open = true" x-bind:aria-expanded="open" aria-controls="mobile-menu"
                class="btn-secondary ml-auto shrink-0 px-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="square" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            Menú
        </button>
    </div>

    <div x-show="open" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Menú">
        <div x-show="open" x-transition.opacity x-on:click="open = false" class="absolute inset-0 bg-neutral-900/50"></div>

        <nav id="mobile-menu"
             x-show="open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             x-on:click="if ($event.target.closest('a')) open = false"
             class="relative flex h-full w-[84%] max-w-[320px] flex-col overflow-y-auto border-r-2 border-ink bg-ground">
            <div class="flex items-center gap-3 border-b-2 border-ink p-4">
                <div class="min-w-0">
                    <div class="text-[15px] font-extrabold">Contabilidad</div>
                    <div class="text-[11px] uppercase tracking-[0.08em] text-neutral-600">por eventos</div>
                </div>
                <button type="button" x-on:click="open = false" aria-label="Cerrar menú"
                        class="ml-auto flex h-9 w-9 shrink-0 items-center justify-center text-2xl leading-none text-neutral-700 hover:bg-ink/5 hover:text-ink">
                    ×
                </button>
            </div>

            <x-nav-links :groups="$groups" mobile />

            <x-nav-user class="mt-auto" />
        </nav>
    </div>
</div>
