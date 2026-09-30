<div {{ $attributes->merge(['class' => 'flex items-center gap-2 border-t-2 border-ink p-4']) }}>
    <div class="flex h-7 w-7 shrink-0 items-center justify-center border border-neutral-400 text-[11px] font-extrabold">
        {{ mb_strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
    </div>
    <div class="min-w-0 text-xs leading-tight">
        <div class="truncate font-extrabold">{{ auth()->user()?->name }}</div>
        <div class="flex gap-3">
            <a href="{{ route('profile') }}" wire:navigate class="text-[11px] text-neutral-700 hover:text-accent">Perfil</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-[11px] text-neutral-700 hover:text-accent">Salir</button>
            </form>
        </div>
    </div>
</div>
