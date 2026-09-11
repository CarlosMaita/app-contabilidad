<div>
    <x-page-header title="Estados financieros" route="/reports · Reports" />

    <div class="flex flex-col gap-4 p-6">
        @if (session('status'))
            <div class="note-accent text-sm">{{ session('status') }}</div>
        @endif

        <div class="flex">
            <button type="button" wire:click="setTab('balance')"
                    class="border border-ink px-3.5 py-2 text-left text-[13px] font-extrabold {{ $tab === 'balance' ? 'bg-ink text-ground' : 'bg-transparent hover:bg-ink/5' }}">
                Balance general
            </button>
            <button type="button" wire:click="setTab('pnl')"
                    class="border border-l-0 border-ink px-3.5 py-2 text-left text-[13px] font-extrabold {{ $tab === 'pnl' ? 'bg-ink text-ground' : 'bg-transparent hover:bg-ink/5' }}">
                Estado de resultados
            </button>
        </div>

        @if ($tab === 'balance' && $balance)
            <div class="panel flex flex-col gap-4 p-4 sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <x-input-label value="Al día" class="text-[10px]" />
                            <x-text-input type="date" class="mt-1 w-[150px]" wire:model.live="asOf" />
                        </div>
                        <label class="flex items-center gap-2 pb-2 text-xs text-neutral-800">
                            <input type="checkbox" wire:model.live="showZero" style="border-radius: 0;"
                                   class="border-neutral-400 text-accent focus:ring-accent">
                            Mostrar cuentas en cero
                        </label>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('exports.balance', ['format' => 'pdf', 'as_of' => $asOf, 'show_zero' => $showZero ? 1 : 0]) }}" class="btn-secondary">PDF</a>
                        <a href="{{ route('exports.balance', ['format' => 'xlsx', 'as_of' => $asOf, 'show_zero' => $showZero ? 1 : 0]) }}" class="btn-secondary">XLSX</a>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        @php($section = $balance['sections']['asset'])
                        <h3 class="mb-2 border-b-2 border-ink pb-1 text-sm font-extrabold uppercase tracking-[0.06em]">{{ $section['label'] }}</h3>
                        <table class="w-full text-[13px]">
                            <tbody>
                                @foreach ($section['rows'] as $row)
                                    <tr>
                                        <td class="py-0.5" style="padding-left: {{ $row['level'] * 18 }}px">
                                            <span class="font-mono text-xs text-neutral-600">{{ $row['account']->code }}</span>
                                            <span class="{{ $row['level'] === 0 ? 'font-extrabold' : '' }}">{{ $row['account']->name }}</span>
                                        </td>
                                        <td class="py-0.5 text-right font-mono">{{ number_format((float) $row['amount'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td class="border-t-2 border-ink py-1.5 font-extrabold">Total {{ $section['label'] }}</td>
                                    <td class="border-t-2 border-ink py-1.5 text-right font-mono font-extrabold">{{ number_format((float) $section['total'], 2, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-6">
                        @foreach (['liability', 'equity'] as $key)
                            @php($section = $balance['sections'][$key])
                            <div>
                                <h3 class="mb-2 border-b-2 border-ink pb-1 text-sm font-extrabold uppercase tracking-[0.06em]">{{ $section['label'] }}</h3>
                                <table class="w-full text-[13px]">
                                    <tbody>
                                        @foreach ($section['rows'] as $row)
                                            <tr>
                                                <td class="py-0.5" style="padding-left: {{ $row['level'] * 18 }}px">
                                                    <span class="font-mono text-xs text-neutral-600">{{ $row['account']->code }}</span>
                                                    <span class="{{ $row['level'] === 0 ? 'font-extrabold' : '' }}">{{ $row['account']->name }}</span>
                                                </td>
                                                <td class="py-0.5 text-right font-mono">{{ number_format((float) $row['amount'], 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                        @if ($key === 'equity')
                                            <tr>
                                                <td class="py-0.5 italic text-neutral-700">Resultado del período</td>
                                                <td class="py-0.5 text-right font-mono italic">{{ number_format((float) $balance['result'], 2, ',', '.') }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td class="border-t-2 border-ink py-1.5 font-extrabold">Total {{ $section['label'] }}{{ $key === 'equity' ? ' + Resultado' : '' }}</td>
                                            <td class="border-t-2 border-ink py-1.5 text-right font-mono font-extrabold">
                                                {{ number_format((float) ($key === 'equity' ? bcadd($section['total'], $balance['result'], 2) : $section['total']), 2, ',', '.') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="border px-4 py-3 text-[13px] font-extrabold {{ $balance['check']['balanced'] ? 'border-ink' : 'border-accent text-accent-600' }}">
                    Activo = Pasivo + Patrimonio + Resultado:
                    <span class="font-mono">{{ number_format((float) $balance['check']['assets'], 2, ',', '.') }}</span> vs
                    <span class="font-mono">{{ number_format((float) $balance['check']['liabilities_equity'], 2, ',', '.') }}</span>
                    {{ $balance['check']['balanced'] ? '✓' : '✗' }}
                </div>
            </div>
        @endif

        @if ($tab === 'pnl' && $pnl)
            <div class="panel flex flex-col gap-4 p-4 sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <x-input-label value="Desde" class="text-[10px]" />
                            <x-text-input type="date" class="mt-1 w-[150px]" wire:model.live="from" />
                        </div>
                        <div>
                            <x-input-label value="Hasta" class="text-[10px]" />
                            <x-text-input type="date" class="mt-1 w-[150px]" wire:model.live="to" />
                        </div>
                        <label class="flex items-center gap-2 pb-2 text-xs text-neutral-800">
                            <input type="checkbox" wire:model.live="showZero" style="border-radius: 0;"
                                   class="border-neutral-400 text-accent focus:ring-accent">
                            Mostrar cuentas en cero
                        </label>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('exports.pnl', ['format' => 'pdf', 'from' => $from, 'to' => $to, 'show_zero' => $showZero ? 1 : 0]) }}" class="btn-secondary">PDF</a>
                        <a href="{{ route('exports.pnl', ['format' => 'xlsx', 'from' => $from, 'to' => $to, 'show_zero' => $showZero ? 1 : 0]) }}" class="btn-secondary">XLSX</a>
                    </div>
                </div>

                @php($money = fn ($v) => number_format((float) $v, 2, ',', '.'))

                <table class="w-full text-[13px]">
                    <tbody>
                        @foreach ($pnl['cascade'] as $item)
                            @if ($item[0] === 'section')
                                @php($section = $pnl['sections'][$item[1]])
                                @if (count($section['rows']) > 0 || (float) $section['total'] != 0)
                                    <tr>
                                        <td class="pt-3 pb-1">
                                            <span class="kicker">{{ $item[2] === '−' ? '(−) ' : '' }}{{ $section['label'] }}</span>
                                        </td>
                                        <td class="pt-3 pb-1 text-right font-mono text-neutral-700">{{ $money($section['total']) }}</td>
                                        <td class="w-24"></td>
                                    </tr>
                                    @foreach ($section['rows'] as $row)
                                        <tr>
                                            <td class="py-0.5" style="padding-left: {{ 12 + $row['level'] * 18 }}px">
                                                <span class="font-mono text-xs text-neutral-600">{{ $row['account']->code }}</span>
                                                <span class="{{ $row['level'] === 0 ? 'font-semibold' : 'text-neutral-800' }}">{{ $row['account']->name }}</span>
                                            </td>
                                            <td class="py-0.5 text-right font-mono {{ (float) $row['amount'] == 0 ? 'text-neutral-500' : '' }}">{{ $money($row['amount']) }}</td>
                                            <td></td>
                                        </tr>
                                    @endforeach
                                @endif
                            @elseif ($item[0] === 'line')
                                <tr>
                                    <td class="border-t-2 border-ink py-1.5 font-extrabold">{{ $item[2] }}</td>
                                    <td class="border-t-2 border-ink py-1.5 text-right font-mono font-extrabold {{ (float) $pnl['lines'][$item[1]] < 0 ? 'text-accent-600' : '' }}">
                                        {{ $money($pnl['lines'][$item[1]]) }}
                                    </td>
                                    <td class="w-24 border-t-2 border-ink py-1.5 pl-3 text-right text-xs text-neutral-600">
                                        @if (($item[3] ?? null) !== null && $pnl['margins'][$item[3]] !== null)
                                            {{ str_replace('.', ',', $pnl['margins'][$item[3]]) }}%
                                        @endif
                                    </td>
                                </tr>
                            @else
                                <tr>
                                    <td class="border-t border-neutral-300 py-1 font-semibold">{{ $item[2] }}</td>
                                    <td class="border-t border-neutral-300 py-1 text-right font-mono font-semibold {{ (float) $pnl['lines'][$item[1]] < 0 ? 'text-accent-600' : '' }}">
                                        {{ $money($pnl['lines'][$item[1]]) }}
                                    </td>
                                    <td class="border-t border-neutral-300"></td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>

                <div class="border px-4 py-3 text-[13px] font-extrabold {{ (float) $pnl['lines']['net'] >= 0 ? 'border-ink' : 'border-accent text-accent-600' }}">
                    Resultado Neto del período: <span class="font-mono">{{ $money($pnl['lines']['net']) }}</span>
                    @if ($pnl['margins']['net'] !== null)
                        · Margen neto: <span class="font-mono">{{ str_replace('.', ',', $pnl['margins']['net']) }}%</span>
                    @endif
                </div>
            </div>
        @endif

        <div class="panel flex flex-col gap-3 p-4 sm:p-6">
            <h3 class="text-sm font-extrabold">Cierre de período</h3>
            <p class="max-w-[70ch] text-[13px] text-neutral-700">
                Con el período cerrado no se puede registrar, generar ni revertir ningún asiento con fecha
                igual o anterior al día indicado. Dejalo vacío para no bloquear fechas.
            </p>
            <form wire:submit="saveClosedUntil" class="flex items-end gap-3">
                <div>
                    <x-input-label value="Cerrado hasta (inclusive)" class="text-[10px]" />
                    <x-text-input type="date" class="mt-1 w-[150px]" wire:model="closedUntil" />
                    <x-input-error :messages="$errors->get('closedUntil')" class="mt-1" />
                </div>
                <x-primary-button type="submit">Guardar</x-primary-button>
            </form>
        </div>
    </div>
</div>
