<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Libro diario</h2>
                <div class="flex gap-2 text-sm">
                    <a href="{{ route('exports.journal', ['format' => 'pdf', 'search' => $search, 'from' => $from, 'to' => $to]) }}"
                       class="text-indigo-600 hover:underline">PDF</a>
                    <a href="{{ route('exports.journal', ['format' => 'xlsx', 'search' => $search, 'from' => $from, 'to' => $to]) }}"
                       class="text-indigo-600 hover:underline">XLSX</a>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4 space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="col-span-2">
                        <x-input-label value="Buscar" class="text-xs" />
                        <x-text-input type="search" class="mt-1 block w-full text-sm"
                                      placeholder="N°, descripción, cuenta u operación…"
                                      wire:model.live.debounce.300ms="search" />
                    </div>
                    <div>
                        <x-input-label value="Desde" class="text-xs" />
                        <x-text-input type="date" class="mt-1 block w-full text-sm" wire:model.live="from" />
                    </div>
                    <div>
                        <x-input-label value="Hasta" class="text-xs" />
                        <x-text-input type="date" class="mt-1 block w-full text-sm" wire:model.live="to" />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="px-3 py-2">N°</th>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2">Descripción</th>
                                <th class="px-3 py-2">Origen</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2 text-right">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($entries as $entry)
                                <tr wire:key="entry-{{ $entry->id }}" class="{{ $entry->status->value === 'reversed' ? 'opacity-60' : '' }}">
                                    <td class="px-3 py-2 font-mono">{{ $entry->number }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $entry->date->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">
                                        {{ $entry->description }}
                                        @if ($entry->reverses_entry_id)
                                            <span class="text-xs text-gray-500">(revierte #{{ $entry->reverses?->number }})</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-600">
                                        {{ $entry->execution?->operationType?->name ?? 'Manual' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $entry->status->value === 'posted' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' }}">
                                            {{ $entry->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" class="text-indigo-600 hover:underline"
                                                wire:click="toggleExpand({{ $entry->id }})">
                                            {{ $expandedId === $entry->id ? 'Ocultar' : 'Ver' }}
                                        </button>
                                    </td>
                                </tr>
                                @if ($expandedId === $entry->id)
                                    <tr class="bg-gray-50">
                                        <td colspan="6" class="px-3 py-3">
                                            <table class="min-w-full text-xs">
                                                <thead>
                                                    <tr class="text-left uppercase text-gray-500">
                                                        <th class="px-2 py-1">Cuenta</th>
                                                        <th class="px-2 py-1">Memo</th>
                                                        <th class="px-2 py-1 text-right">Debe</th>
                                                        <th class="px-2 py-1 text-right">Haber</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200">
                                                    @foreach ($entry->lines as $line)
                                                        <tr>
                                                            <td class="px-2 py-1">
                                                                <span class="font-mono">{{ $line->account->code }}</span>
                                                                {{ $line->account->name }}
                                                            </td>
                                                            <td class="px-2 py-1 text-gray-500">{{ $line->memo ?? '—' }}</td>
                                                            <td class="px-2 py-1 text-right font-mono">{{ $line->debit != 0 ? number_format((float) $line->debit, 2) : '' }}</td>
                                                            <td class="px-2 py-1 text-right font-mono">{{ $line->credit != 0 ? number_format((float) $line->credit, 2) : '' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-500">
                                        No hay asientos con estos filtros.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $entries->links() }}
            </div>
        </div>
    </div>
</div>
