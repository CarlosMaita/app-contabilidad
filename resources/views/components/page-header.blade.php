@props(['title', 'route' => null])

<div class="flex flex-wrap items-baseline gap-4 border-b-2 border-ink px-6 py-4">
    <h1 class="text-[26px] font-extrabold leading-tight tracking-tight">{{ $title }}</h1>
    @if ($route)
        <span class="font-mono text-xs text-neutral-700">{{ $route }}</span>
    @endif
    <span class="ml-auto text-xs text-neutral-700">Ejercicio {{ now()->year }} · ARS</span>
</div>
