<div>
    <x-page-header title="Libro diario" route="/journal · JournalBook" />

    <div class="flex flex-col gap-4 p-6">
        @if (session('status'))
            <div class="note-accent text-sm">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="note-accent text-sm font-extrabold text-accent-600">{{ session('error') }}</div>
        @endif

        <div class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label value="Desde" class="text-[10px]" />
                <x-text-input type="date" class="mt-1 w-[140px]" wire:model.live="from" />
            </div>
            <div>
                <x-input-label value="Hasta" class="text-[10px]" />
                <x-text-input type="date" class="mt-1 w-[140px]" wire:model.live="to" />
            </div>
            <div class="min-w-[240px] grow sm:max-w-[340px]">
                <x-input-label value="Buscar" class="text-[10px]" />
                <x-text-input type="search" class="mt-1" placeholder="Número, descripción, cuenta u operación"
                              wire:model.live.debounce.300ms="search" />
            </div>
            <a href="{{ route('exports.journal', ['format' => 'pdf', 'search' => $search, 'from' => $from, 'to' => $to]) }}"
               class="btn-secondary ml-auto">PDF</a>
            <a href="{{ route('exports.journal', ['format' => 'xlsx', 'search' => $search, 'from' => $from, 'to' => $to]) }}"
               class="btn-secondary">XLSX</a>
            <a href="{{ route('journal.create') }}" wire:navigate class="btn-primary">Nuevo asiento</a>
        </div>

        <div class="panel overflow-x-auto">
            <table class="w-full border-collapse text-[13px]">
                <thead>
                    <tr class="border-b-2 border-ink">
                        <th class="th">N.º</th>
                        <th class="th">Fecha</th>
                        <th class="th">Descripción</th>
                        <th class="th">Origen</th>
                        <th class="th">Estado</th>
                        <th class="th text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr class="row-hover {{ $entry->status->value === 'reversed' ? 'opacity-60' : '' }}" wire:key="entry-{{ $entry->id }}">
                            <td class="td font-mono font-extrabold">{{ $entry->number }}</td>
                            <td class="td whitespace-nowrap font-mono">{{ $entry->date->format('d/m/Y') }}</td>
                            <td class="td">
                                {{ $entry->description }}
                                @if ($entry->reverses_entry_id)
                                    <span class="text-xs text-neutral-600">(revierte #{{ $entry->reverses?->number }})</span>
                                @endif
                            </td>
                            <td class="td text-xs text-neutral-700">{{ $entry->execution?->operationType?->name ?? 'Manual' }}</td>
                            <td class="td">
                                <span class="badge {{ $entry->status->value === 'posted' ? 'border-ink text-ink' : 'border-neutral-400 text-neutral-600' }}">
                                    {{ $entry->status->label() }}
                                </span>
                            </td>
                            <td class="td whitespace-nowrap text-right">
                                <button type="button" class="btn-ghost text-xs" wire:click="toggleExpand({{ $entry->id }})">
                                    {{ $expandedId === $entry->id ? 'Ocultar' : 'Ver' }}
                                </button>
                                @if ($entry->status->value === 'posted')
                                    <button type="button" class="btn-ghost text-xs" wire:click="reverse({{ $entry->id }})"
                                            wire:confirm="¿Revertir el asiento #{{ $entry->number }}? Se genera un contra-asiento{{ $entry->operation_execution_id ? ' y se anula la operación de origen' : '' }}.">
                                        Revertir
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @if ($expandedId === $entry->id)
                            <tr class="bg-neutral-100">
                                <td colspan="6" class="border-b border-neutral-300 px-4 py-3">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr>
                                                <th class="px-2 py-1 text-left text-[10px] font-normal uppercase tracking-[0.1em] text-neutral-600">Cuenta</th>
                                                <th class="px-2 py-1 text-left text-[10px] font-normal uppercase tracking-[0.1em] text-neutral-600">Memo</th>
                                                <th class="px-2 py-1 text-right text-[10px] font-normal uppercase tracking-[0.1em] text-neutral-600">Debe</th>
                                                <th class="px-2 py-1 text-right text-[10px] font-normal uppercase tracking-[0.1em] text-neutral-600">Haber</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($entry->lines as $line)
                                                <tr class="border-t border-neutral-200">
                                                    <td class="px-2 py-1.5"><span class="font-mono">{{ $line->account->code }}</span> {{ $line->account->name }}</td>
                                                    <td class="px-2 py-1.5 text-neutral-600">{{ $line->memo ?? '—' }}</td>
                                                    <td class="px-2 py-1.5 text-right font-mono">{{ $line->debit != 0 ? number_format((float) $line->debit, 2, ',', '.') : '' }}</td>
                                                    <td class="px-2 py-1.5 text-right font-mono">{{ $line->credit != 0 ? number_format((float) $line->credit, 2, ',', '.') : '' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="td py-8 text-center text-neutral-700">No hay asientos con estos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $entries->links() }}

        <div class="text-xs text-neutral-700">
            Asiento inmutable: se corrige con reversión. Numeración correlativa por usuario.
        </div>
    </div>
</div>
