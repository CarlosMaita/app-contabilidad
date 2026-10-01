<div>
    <x-page-header title="Dashboard" route="/dashboard" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="grid grid-cols-2 border-2 border-ink lg:grid-cols-4">
            <div class="border-b border-r border-neutral-300 bg-white p-3 sm:p-4 lg:border-b-0">
                <div class="kicker">Activo total</div>
                <div class="mt-1 font-mono text-[17px] font-extrabold leading-tight [overflow-wrap:anywhere] sm:mt-1.5 sm:text-[26px]">{{ number_format((float) $assets, 2, ',', '.') }}</div>
                <div class="text-[11px] {{ $balanced ? 'text-neutral-700' : 'font-extrabold text-accent-600' }}">
                    {{ $balanced ? 'el balance cuadra' : 'el balance NO cuadra' }}
                </div>
            </div>
            <div class="border-b border-neutral-300 bg-white p-3 sm:p-4 lg:border-b-0 lg:border-r">
                <div class="kicker">Ingresos del mes</div>
                <div class="mt-1 font-mono text-[17px] font-extrabold leading-tight [overflow-wrap:anywhere] sm:mt-1.5 sm:text-[26px]">{{ number_format((float) $monthIncome, 2, ',', '.') }}</div>
                <div class="text-[11px] text-neutral-700">{{ now()->translatedFormat('F Y') }}</div>
            </div>
            <div class="border-r border-neutral-300 bg-white p-3 sm:p-4">
                <div class="kicker">Gastos del mes</div>
                <div class="mt-1 font-mono text-[17px] font-extrabold leading-tight [overflow-wrap:anywhere] sm:mt-1.5 sm:text-[26px]">{{ number_format((float) $monthExpense, 2, ',', '.') }}</div>
                <div class="text-[11px] text-neutral-700">{{ now()->translatedFormat('F Y') }}</div>
            </div>
            <div class="bg-white p-3 sm:p-4">
                <div class="kicker">Resultado del mes</div>
                <div class="mt-1 font-mono text-[17px] font-extrabold leading-tight [overflow-wrap:anywhere] sm:mt-1.5 sm:text-[26px] {{ (float) $monthResult < 0 ? 'text-accent-600' : '' }}">
                    {{ ((float) $monthResult >= 0 ? '+ ' : '') . number_format((float) $monthResult, 2, ',', '.') }}
                </div>
                <div class="text-[11px] text-neutral-700">ingresos − gastos</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div class="panel min-w-0">
                <div class="panel-head">
                    <span class="panel-title"><span class="sm:hidden">Asientos</span><span class="hidden sm:inline">Últimos asientos</span></span>
                    <div class="ml-auto flex gap-2">
                        <a href="{{ route('journal.index') }}" wire:navigate class="btn-secondary px-2.5 py-1.5 text-xs sm:px-3.5 sm:py-2 sm:text-[13px]">
                            <span class="sm:hidden">Diario</span><span class="hidden sm:inline">Ver el diario</span>
                        </a>
                        <a href="{{ route('journal.create') }}" wire:navigate class="btn-primary px-2.5 py-1.5 text-xs sm:px-3.5 sm:py-2 sm:text-[13px]">Registrar asiento</a>
                    </div>
                </div>

                {{-- Móvil: lista apilada --}}
                <ul class="divide-y divide-neutral-200 sm:hidden">
                    @forelse ($lastEntries as $entry)
                        <li class="px-4 py-2.5">
                            <div class="text-[13px] leading-snug">{{ $entry->description }}</div>
                            <div class="mt-0.5 flex gap-2 font-mono text-[11px] text-neutral-600">
                                <span class="font-extrabold text-ink">#{{ $entry->number }}</span>
                                <span>{{ $entry->date->format('d/m/Y') }}</span>
                                <span class="truncate font-sans">· {{ $entry->execution?->operationType?->name ?? 'Manual' }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-[13px] text-neutral-700">
                            Todavía no hay asientos.
                            <a href="{{ route('operations.index') }}" wire:navigate class="text-accent hover:text-accent-700">Ejecutá tu primera operación</a>.
                        </li>
                    @endforelse
                </ul>

                <table class="hidden w-full border-collapse text-[13px] sm:table">
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

            <div class="flex min-w-0 flex-col gap-4 sm:gap-6">
                <div class="panel p-3 sm:p-4">
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

                <div class="panel p-3 sm:p-4">
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
