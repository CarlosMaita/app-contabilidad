<div>
    <x-page-header title="Dashboard" route="/dashboard" />

    <div class="flex flex-col gap-6 p-6">
        <div class="grid grid-cols-1 border-2 border-ink sm:grid-cols-2 lg:grid-cols-4">
            <div class="border-b border-neutral-300 bg-white p-4 sm:border-b-0 sm:border-r">
                <div class="kicker">Activo total</div>
                <div class="mt-1.5 font-mono text-[26px] font-extrabold leading-tight">{{ number_format((float) $assets, 2, ',', '.') }}</div>
                <div class="text-[11px] {{ $balanced ? 'text-neutral-700' : 'font-extrabold text-accent-600' }}">
                    {{ $balanced ? 'el balance cuadra' : 'el balance NO cuadra' }}
                </div>
            </div>
            <div class="border-b border-neutral-300 bg-white p-4 sm:border-b-0 lg:border-r">
                <div class="kicker">Ingresos del mes</div>
                <div class="mt-1.5 font-mono text-[26px] font-extrabold leading-tight">{{ number_format((float) $monthIncome, 2, ',', '.') }}</div>
                <div class="text-[11px] text-neutral-700">{{ now()->translatedFormat('F Y') }}</div>
            </div>
            <div class="border-b border-neutral-300 bg-white p-4 sm:border-b-0 sm:border-r">
                <div class="kicker">Gastos del mes</div>
                <div class="mt-1.5 font-mono text-[26px] font-extrabold leading-tight">{{ number_format((float) $monthExpense, 2, ',', '.') }}</div>
                <div class="text-[11px] text-neutral-700">{{ now()->translatedFormat('F Y') }}</div>
            </div>
            <div class="bg-white p-4">
                <div class="kicker">Resultado del mes</div>
                <div class="mt-1.5 font-mono text-[26px] font-extrabold leading-tight {{ (float) $monthResult < 0 ? 'text-accent-600' : '' }}">
                    {{ ((float) $monthResult >= 0 ? '+ ' : '') . number_format((float) $monthResult, 2, ',', '.') }}
                </div>
                <div class="text-[11px] text-neutral-700">ingresos − gastos</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div class="panel min-w-0">
                <div class="panel-head">
                    <span class="panel-title">Últimos asientos</span>
                    <a href="{{ route('journal.index') }}" wire:navigate class="btn-secondary ml-auto">Ver el diario</a>
                </div>
                <table class="w-full border-collapse text-[13px]">
                    <tbody>
                        @forelse ($lastEntries as $entry)
                            <tr class="row-hover">
                                <td class="td font-mono font-extrabold">#{{ $entry->number }}</td>
                                <td class="td whitespace-nowrap font-mono">{{ $entry->date->format('d/m/Y') }}</td>
                                <td class="td">{{ $entry->description }}</td>
                                <td class="td text-xs text-neutral-700">{{ $entry->execution?->operationType?->name ?? 'Manual' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="td py-8 text-center text-neutral-700" colspan="4">
                                    Todavía no hay asientos.
                                    <a href="{{ route('operations.index') }}" wire:navigate class="text-accent hover:text-accent-700">Ejecutá tu primera operación</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex min-w-0 flex-col gap-6">
                <div class="panel p-4">
                    <div class="mb-2.5 text-sm font-extrabold">Verificación</div>
                    <div class="flex justify-between gap-3 border-b border-neutral-200 py-1.5 text-[13px]">
                        <span class="text-neutral-800">Activo</span>
                        <span class="font-mono font-extrabold">{{ number_format((float) $assets, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between gap-3 border-b border-neutral-200 py-1.5 text-[13px]">
                        <span class="text-neutral-800">Pasivo + Patrimonio + Resultado</span>
                        <span class="font-mono font-extrabold">{{ number_format((float) $liabilitiesEquity, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between gap-3 py-1.5 text-[13px]">
                        <span class="text-neutral-800">Diferencia</span>
                        <span class="font-mono font-extrabold {{ $balanced ? '' : 'text-accent-600' }}">{{ number_format((float) $difference, 2, ',', '.') }}</span>
                    </div>
                </div>

                <div class="panel p-4">
                    <div class="mb-2.5 text-sm font-extrabold">Atención</div>
                    @if ($attention > 0)
                        <div class="text-[13px] text-neutral-800">
                            {{ $attention }} evento(s) sin contabilizar (pendientes, sin mapeo o con error).
                        </div>
                        <a href="{{ route('events.index') }}" wire:navigate class="btn-primary mt-3">Ver el log de eventos</a>
                    @else
                        <div class="text-[13px] text-neutral-800">Todos los eventos están contabilizados.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
