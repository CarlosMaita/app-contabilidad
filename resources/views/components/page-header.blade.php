@props(['title', 'route' => null])

<div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b-2 border-ink px-4 py-3 sm:px-6 sm:py-4">
    <h1 class="text-[22px] font-extrabold leading-tight tracking-tight sm:text-[26px]">{{ $title }}</h1>
    @if ($route)
        <span class="hidden font-mono text-xs text-neutral-700 sm:inline">{{ $route }}</span>
    @endif
    <div class="ml-auto flex flex-wrap items-center gap-3">
        <span class="text-xs text-neutral-700">Ejercicio {{ now()->year }} · ARS</span>
        {{ $slot }}
    </div>
</div>
