<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Libro mayor</h2>
                @if ($account !== null)
                    <div class="flex gap-2 text-sm">
                        <a href="{{ route('exports.ledger', ['format' => 'pdf', 'account_id' => $account->id, 'from' => $from, 'to' => $to]) }}"
                           class="text-indigo-600 hover:underline">PDF</a>
                        <a href="{{ route('exports.ledger', ['format' => 'xlsx', 'account_id' => $account->id, 'from' => $from, 'to' => $to]) }}"
                           class="text-indigo-600 hover:underline">XLSX</a>
                    </div>
                @endif
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4 space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="col-span-2">
                        <x-input-label value="Cuenta" class="text-xs" />
                        <select wire:model.live="accountId"
                                class="mt-1 block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— Elegir cuenta —</option>
                            @foreach ($accounts as $a)
                                <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                            @endforeach
                        </select>
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

                @if ($account === null)
                    <p class="py-8 text-center text-gray-500">Elegí una cuenta para ver sus movimientos.</p>
                @else
                    <div class="flex items-baseline justify-between">
                        <h3 class="font-medium text-gray-800">
                            <span class="font-mono">{{ $account->code }}</span> {{ $account->name }}
                            <span class="text-xs text-gray-500">({{ $account->type->label() }} — saldo {{ $account->type->isDebitNature() ? 'deudor' : 'acreedor' }})</span>
                        </h3>
                        <p class="text-sm text-gray-600">
                            Saldo inicial: <span class="font-mono font-medium">{{ number_format((float) $result['opening'], 2) }}</span>
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase text-gray-500">
                                    <th class="px-3 py-2">Fecha</th>
                                    <th class="px-3 py-2">Asiento</th>
                                    <th class="px-3 py-2">Descripción</th>
                                    <th class="px-3 py-2 text-right">Debe</th>
                                    <th class="px-3 py-2 text-right">Haber</th>
                                    <th class="px-3 py-2 text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($result['movements'] as $m)
                                    <tr>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $m->date->format('d/m/Y') }}</td>
                                        <td class="px-3 py-2 font-mono">#{{ $m->number }}</td>
                                        <td class="px-3 py-2">
                                            {{ $m->description }}
                                            @if ($m->memo)
                                                <span class="text-xs text-gray-500">— {{ $m->memo }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono">{{ $m->debit != 0 ? number_format((float) $m->debit, 2) : '' }}</td>
                                        <td class="px-3 py-2 text-right font-mono">{{ $m->credit != 0 ? number_format((float) $m->credit, 2) : '' }}</td>
                                        <td class="px-3 py-2 text-right font-mono font-medium">{{ number_format((float) $m->balance, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-3 py-8 text-center text-gray-500">
                                            Sin movimientos en el período.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="font-semibold border-t">
                                    <td colspan="5" class="px-3 py-2 text-right">Saldo final</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $result['closing'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
