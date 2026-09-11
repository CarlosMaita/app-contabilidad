<div>
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Estados financieros</h2>
                <div class="flex rounded-md shadow-sm">
                    <button type="button" wire:click="setTab('balance')"
                            class="px-4 py-2 text-sm font-medium border rounded-l-md {{ $tab === 'balance' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                        Balance general
                    </button>
                    <button type="button" wire:click="setTab('pnl')"
                            class="px-4 py-2 text-sm font-medium border-t border-b border-r rounded-r-md {{ $tab === 'pnl' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                        Estado de resultados
                    </button>
                </div>
            </div>

            @if ($tab === 'balance' && $balance)
                <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                    <div class="flex items-end justify-between gap-4 flex-wrap">
                        <div>
                            <x-input-label value="Al día" class="text-xs" />
                            <x-text-input type="date" class="mt-1 block text-sm" wire:model.live="asOf" />
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('exports.balance', ['format' => 'pdf', 'as_of' => $asOf]) }}"
                               class="text-sm text-indigo-600 hover:underline">Descargar PDF</a>
                            <a href="{{ route('exports.balance', ['format' => 'xlsx', 'as_of' => $asOf]) }}"
                               class="text-sm text-indigo-600 hover:underline">Descargar XLSX</a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            @foreach (['asset'] as $key)
                                @php($section = $balance['sections'][$key])
                                <h3 class="font-semibold text-gray-800 border-b pb-1 mb-2">{{ $section['label'] }}</h3>
                                <table class="min-w-full text-sm">
                                    <tbody>
                                        @foreach ($section['rows'] as $row)
                                            <tr>
                                                <td class="py-0.5" style="padding-left: {{ $row['level'] * 1.25 }}rem">
                                                    <span class="font-mono text-xs text-gray-500">{{ $row['account']->code }}</span>
                                                    {{ $row['account']->name }}
                                                </td>
                                                <td class="py-0.5 text-right font-mono">{{ number_format((float) $row['amount'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                        <tr class="font-semibold border-t">
                                            <td class="py-1">Total {{ $section['label'] }}</td>
                                            <td class="py-1 text-right font-mono">{{ number_format((float) $section['total'], 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            @endforeach
                        </div>

                        <div class="space-y-6">
                            @foreach (['liability', 'equity'] as $key)
                                @php($section = $balance['sections'][$key])
                                <div>
                                    <h3 class="font-semibold text-gray-800 border-b pb-1 mb-2">{{ $section['label'] }}</h3>
                                    <table class="min-w-full text-sm">
                                        <tbody>
                                            @foreach ($section['rows'] as $row)
                                                <tr>
                                                    <td class="py-0.5" style="padding-left: {{ $row['level'] * 1.25 }}rem">
                                                        <span class="font-mono text-xs text-gray-500">{{ $row['account']->code }}</span>
                                                        {{ $row['account']->name }}
                                                    </td>
                                                    <td class="py-0.5 text-right font-mono">{{ number_format((float) $row['amount'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                            @if ($key === 'equity')
                                                <tr>
                                                    <td class="py-0.5 italic text-gray-600">Resultado del período</td>
                                                    <td class="py-0.5 text-right font-mono italic">{{ number_format((float) $balance['result'], 2) }}</td>
                                                </tr>
                                            @endif
                                            <tr class="font-semibold border-t">
                                                <td class="py-1">Total {{ $section['label'] }}{{ $key === 'equity' ? ' + Resultado' : '' }}</td>
                                                <td class="py-1 text-right font-mono">
                                                    {{ number_format((float) ($key === 'equity' ? bcadd($section['total'], $balance['result'], 2) : $section['total']), 2) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-md p-3 text-sm {{ $balance['check']['balanced'] ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                        {{ $balance['check']['balanced'] ? '✓' : '✗' }}
                        Activo = Pasivo + Patrimonio + Resultado:
                        <span class="font-mono">{{ number_format((float) $balance['check']['assets'], 2) }}</span> vs
                        <span class="font-mono">{{ number_format((float) $balance['check']['liabilities_equity'], 2) }}</span>
                    </div>
                </div>
            @endif

            @if ($tab === 'pnl' && $pnl)
                <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                    <div class="flex items-end justify-between gap-4 flex-wrap">
                        <div class="flex gap-3">
                            <div>
                                <x-input-label value="Desde" class="text-xs" />
                                <x-text-input type="date" class="mt-1 block text-sm" wire:model.live="from" />
                            </div>
                            <div>
                                <x-input-label value="Hasta" class="text-xs" />
                                <x-text-input type="date" class="mt-1 block text-sm" wire:model.live="to" />
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('exports.pnl', ['format' => 'pdf', 'from' => $from, 'to' => $to]) }}"
                               class="text-sm text-indigo-600 hover:underline">Descargar PDF</a>
                            <a href="{{ route('exports.pnl', ['format' => 'xlsx', 'from' => $from, 'to' => $to]) }}"
                               class="text-sm text-indigo-600 hover:underline">Descargar XLSX</a>
                        </div>
                    </div>

                    @foreach (['income' => $pnl['income'], 'expense' => $pnl['expense']] as $key => $section)
                        <div>
                            <h3 class="font-semibold text-gray-800 border-b pb-1 mb-2">{{ $key === 'income' ? 'Ingresos' : 'Gastos' }}</h3>
                            <table class="min-w-full text-sm">
                                <tbody>
                                    @forelse ($section['rows'] as $row)
                                        <tr>
                                            <td class="py-0.5" style="padding-left: {{ $row['level'] * 1.25 }}rem">
                                                <span class="font-mono text-xs text-gray-500">{{ $row['account']->code }}</span>
                                                {{ $row['account']->name }}
                                            </td>
                                            <td class="py-0.5 text-right font-mono">{{ number_format((float) $row['amount'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="py-1 text-gray-500">Sin movimientos.</td><td></td></tr>
                                    @endforelse
                                    <tr class="font-semibold border-t">
                                        <td class="py-1">Total {{ $key === 'income' ? 'Ingresos' : 'Gastos' }}</td>
                                        <td class="py-1 text-right font-mono">{{ number_format((float) $section['total'], 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @endforeach

                    <div class="rounded-md p-3 text-sm font-semibold {{ (float) $pnl['result'] >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                        Resultado neto del período:
                        <span class="font-mono">{{ number_format((float) $pnl['result'], 2) }}</span>
                    </div>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3">
                <h3 class="font-semibold text-gray-800">Cierre de período</h3>
                <p class="text-sm text-gray-500">
                    Con el período cerrado no se puede registrar, generar ni revertir ningún asiento con fecha
                    igual o anterior al día indicado. Dejalo vacío para no bloquear fechas.
                </p>
                <form wire:submit="saveClosedUntil" class="flex items-end gap-3">
                    <div>
                        <x-input-label value="Cerrado hasta (inclusive)" class="text-xs" />
                        <x-text-input type="date" class="mt-1 block text-sm" wire:model="closedUntil" />
                        <x-input-error :messages="$errors->get('closedUntil')" class="mt-1" />
                    </div>
                    <x-primary-button type="submit">Guardar</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</div>
