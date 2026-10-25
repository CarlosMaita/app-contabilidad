@php
    $current = \App\Modules\Shared\Books\CurrentBook::get();
    $books = auth()->user()->books()
        ->orderByRaw("case when kind = 'personal' then 0 else 1 end")
        ->orderBy('name')
        ->get();
    $members = $current && ! $current->isPersonal() ? $current->members()->count() : 0;
@endphp

{{-- Selector de contabilidad: la actual y las demás de las que soy miembro --}}
<div x-data="{ open: false }" class="relative border-b-2 border-ink px-4 py-3" {{ $attributes }}>
    <div class="kicker">Contabilidad</div>
    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open"
            class="mt-1 flex w-full items-center gap-2 border-2 border-ink bg-white px-2.5 py-1.5 text-left text-[13px] font-extrabold hover:bg-neutral-100">
        <span class="min-w-0 grow truncate" data-current-book>{{ $current?->label() }}</span>
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="shrink-0 transition" x-bind:class="open && 'rotate-180'" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    @if ($current && ! $current->isPersonal())
        <div class="mt-1 text-[11px] text-neutral-600">Compartida · {{ $members }} {{ $members === 1 ? 'miembro' : 'miembros' }}</div>
    @endif

    <div x-show="open" x-cloak x-on:click.outside="open = false"
         class="absolute left-4 right-4 z-40 mt-1 border-2 border-ink bg-white">
        <ul class="max-h-72 divide-y divide-neutral-200 overflow-y-auto">
            @foreach ($books as $book)
                <li>
                    <form method="POST" action="{{ route('books.switch', $book) }}">
                        @csrf
                        <button type="submit" @disabled($book->id === $current?->id)
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-[13px] hover:bg-neutral-100 disabled:cursor-default disabled:bg-accent-100">
                            <span class="min-w-0 grow truncate {{ $book->id === $current?->id ? 'font-extrabold' : '' }}">{{ $book->label() }}</span>
                            <span class="shrink-0 text-[10px] uppercase tracking-[0.08em] text-neutral-500">
                                {{ $book->isPersonal() ? 'personal' : ($book->pivot->role === 'owner' ? 'dueño' : 'miembro') }}
                            </span>
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
        <a href="{{ route('books.index') }}" wire:navigate
           class="block border-t-2 border-ink px-3 py-2 text-[12px] font-extrabold text-accent hover:bg-neutral-100">
            Gestionar contabilidades →
        </a>
    </div>
</div>
