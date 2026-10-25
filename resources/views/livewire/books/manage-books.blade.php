<div>
    <x-page-header title="Mis contabilidades" route="/books · ManageBooks" />

    <div class="flex flex-col gap-4 p-4 sm:p-6">
        @if (session('status'))
            <div class="note-accent text-sm">{{ session('status') }}</div>
        @endif

        <p class="max-w-[75ch] text-sm text-neutral-700">
            Cada contabilidad es independiente: su plan de cuentas, asientos, operaciones y reportes.
            Podés llevar la tuya, una familiar o la de un negocio con socios, e invitar a otras personas para gestionarla juntos.
        </p>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
            {{-- Listado --}}
            <div class="flex flex-col gap-4">
                @foreach ($books as $book)
                    @php($isOwner = $book->owner_id === auth()->id())
                    <section class="panel" wire:key="book-{{ $book->id }}">
                        <div class="panel-head flex flex-wrap items-center gap-2">
                            <span class="panel-title">{{ $book->label() }}</span>
                            @if ($book->id === $currentId)
                                <span class="badge border-accent text-accent">Actual</span>
                            @endif
                            <span class="badge border-neutral-400 text-neutral-600">{{ $templates[$book->template] ?? $book->template }}</span>
                            <span class="badge border-neutral-400 text-neutral-600">{{ $book->isPersonal() ? 'personal' : ($isOwner ? 'dueño' : 'miembro') }}</span>
                            @if ($book->id !== $currentId)
                                <form method="POST" action="{{ route('books.switch', $book) }}" class="ml-auto">
                                    @csrf
                                    <button type="submit" class="btn-primary px-3 py-1.5 text-xs">Usar</button>
                                </form>
                            @endif
                        </div>

                        @unless ($book->isPersonal())
                            <div class="flex flex-col gap-3 p-4 text-sm">
                                @if ($isOwner)
                                    <form wire:submit="saveName({{ $book->id }})" class="flex flex-wrap items-end gap-2">
                                        <div class="min-w-[200px] grow">
                                            <x-input-label value="Nombre" class="text-[10px]" />
                                            <x-text-input class="mt-1" wire:model="rename.{{ $book->id }}" placeholder="{{ $book->name }}" />
                                            <x-input-error :messages="$errors->get('rename.'.$book->id)" class="mt-1" />
                                        </div>
                                        <button type="submit" class="btn-secondary px-3 py-2 text-xs">Renombrar</button>
                                    </form>
                                @endif

                                <div>
                                    <div class="kicker">Miembros</div>
                                    <ul class="mt-1 divide-y divide-neutral-200 border border-neutral-300 bg-white">
                                        @foreach ($book->members as $member)
                                            <li class="flex flex-wrap items-center gap-x-3 gap-y-0.5 px-3 py-2" wire:key="m-{{ $book->id }}-{{ $member->id }}">
                                                <span class="font-extrabold">{{ $member->name }}</span>
                                                <span class="text-xs text-neutral-600">{{ $member->email }}</span>
                                                <span class="ml-auto text-[10px] uppercase tracking-[0.08em] text-neutral-500">{{ $member->pivot->role === 'owner' ? 'dueño' : 'miembro' }}</span>
                                                @if ($isOwner && $member->id !== $book->owner_id)
                                                    <button type="button" class="btn-ghost text-xs" wire:click="removeMember({{ $book->id }}, {{ $member->id }})"
                                                            wire:confirm="¿Quitar a {{ $member->name }} de «{{ $book->name }}»? Sus asientos quedan registrados.">Quitar</button>
                                                @endif
                                            </li>
                                        @endforeach
                                        @foreach ($book->invitations as $invitation)
                                            <li class="flex flex-wrap items-center gap-x-3 px-3 py-2 text-neutral-600" wire:key="i-{{ $invitation->id }}">
                                                <span>{{ $invitation->email }}</span>
                                                <span class="badge border-neutral-400 text-neutral-600">pendiente de registro</span>
                                                @if ($isOwner)
                                                    <button type="button" class="btn-ghost ml-auto text-xs" wire:click="cancelInvite({{ $book->id }}, {{ $invitation->id }})">Cancelar</button>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                @if ($isOwner)
                                    <form wire:submit="sendInvite({{ $book->id }})" class="flex flex-wrap items-end gap-2">
                                        <div class="min-w-[220px] grow">
                                            <x-input-label value="Invitar por correo" class="text-[10px]" />
                                            <x-text-input type="email" class="mt-1" wire:model="invite.{{ $book->id }}" placeholder="persona@correo.com" />
                                            <x-input-error :messages="$errors->get('invite.'.$book->id)" class="mt-1" />
                                            <x-input-error :messages="$errors->get('email')" class="mt-1" />
                                        </div>
                                        <button type="submit" class="btn-primary px-3 py-2 text-xs">Invitar</button>
                                    </form>
                                    <p class="text-xs text-neutral-600">
                                        Si la persona ya tiene cuenta, se suma al instante. Si no, se suma sola cuando se registre con ese correo.
                                    </p>
                                    <div class="border-t border-neutral-200 pt-3">
                                        <button type="button" class="btn-ghost text-xs" wire:click="destroy({{ $book->id }})"
                                                wire:confirm="¿Borrar «{{ $book->name }}» con todos sus asientos, cuentas y operaciones? No se puede deshacer.">
                                            Borrar contabilidad
                                        </button>
                                    </div>
                                @else
                                    <div class="border-t border-neutral-200 pt-3">
                                        <button type="button" class="btn-ghost text-xs" wire:click="leave({{ $book->id }})"
                                                wire:confirm="¿Salir de «{{ $book->name }}»? Vas a dejar de verla.">
                                            Salir de esta contabilidad
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @else
                            <p class="p-4 text-sm text-neutral-700">Tu contabilidad privada. Solo vos la ves.</p>
                        @endunless
                    </section>
                @endforeach
            </div>

            {{-- Nueva contabilidad --}}
            <form wire:submit="create" class="panel flex flex-col gap-3 p-4">
                <div class="panel-title">Nueva contabilidad</div>
                <div>
                    <x-input-label for="book-name" value="Nombre" />
                    <x-text-input id="book-name" class="mt-1.5" wire:model="name" placeholder="Familia Maita, Fotografía…" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <fieldset>
                    <legend class="form-label">Plantilla</legend>
                    <div class="mt-1.5 grid gap-2">
                        <label class="flex cursor-pointer gap-2 border-2 border-ink bg-white p-2.5 text-sm has-[:checked]:bg-accent-100">
                            <input type="radio" wire:model="template" value="business" class="mt-0.5 text-accent focus:ring-accent">
                            <span><span class="font-extrabold">Negocio</span><br><span class="text-xs text-neutral-600">Ventas, costos, gastos, IVA y aportes por socio. Ej.: fotografía.</span></span>
                        </label>
                        <label class="flex cursor-pointer gap-2 border-2 border-ink bg-white p-2.5 text-sm has-[:checked]:bg-accent-100">
                            <input type="radio" wire:model="template" value="household" class="mt-0.5 text-accent focus:ring-accent">
                            <span><span class="font-extrabold">Hogar</span><br><span class="text-xs text-neutral-600">Fondo común (Binance), aportes por miembro y gastos de la casa: servicios, comida, remodelaciones.</span></span>
                        </label>
                    </div>
                </fieldset>
                <x-primary-button class="justify-center">Crear y usar</x-primary-button>
                <p class="text-xs text-neutral-600">Después podés invitar a tu familia o socios desde esta misma página.</p>
            </form>
        </div>
    </div>
</div>
