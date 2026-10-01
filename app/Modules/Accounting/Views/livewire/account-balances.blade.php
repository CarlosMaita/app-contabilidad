<div>
    <x-page-header title="Saldos de cuentas" route="/balances · AccountBalances" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        @php($money = fn ($v) => number_format((float) $v, 2, ',', '.'))

        <div class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label value="Al día" class="text-[10px]" />
                <x-text-input type="date" class="mt-1 w-[150px]" wire:model.live="asOf" />
            </div>
            <div class="min-w-[200px] grow sm:max-w-[320px]">
                <x-input-label value="Buscar" class="text-[10px]" />
                <x-text-input type="search" class="mt-1" placeholder="Código o nombre de cuenta"
                              wire:model.live.debounce.300ms="search" />
            </div>
            <p class="w-full text-xs text-neutral-600 sm:ml-auto sm:w-auto">
                {{ $report['count'] }} cuenta(s) con saldo · tocá una para ver sus movimientos
            </p>
        </div>

        @if ($report['count'] === 0)
            <div class="panel p-8 text-center text-[13px] text-neutral-700">
                {{ $search !== '' ? 'Ninguna cuenta con saldo coincide con la búsqueda.' : 'Todavía no hay cuentas con saldo a esta fecha.' }}
            </div>
        @endif

        @foreach ($report['groups'] as $group)
            @if (count($group['rows']) > 0)
                <div class="panel min-w-0">
                    <div class="panel-head">
                        <span class="panel-title">{{ $group['label'] }}</span>
                        <span class="ml-auto font-mono text-sm font-extrabold {{ (float) $group['total'] < 0 ? 'text-accent-600' : '' }}">{{ $money($group['total']) }}</span>
                    </div>
                    <ul class="divide-y divide-neutral-200">
                        @foreach ($group['rows'] as $row)
                            @php($account = $row['account'])
                            <li>
                                <a href="{{ $account->is_auxiliary
                                        ? route('subledgers.index', ['principalId' => $account->parent_id, 'auxiliaryId' => $account->id])
                                        : route('ledger.index', ['accountId' => $account->id]) }}"
                                   wire:navigate
                                   class="flex items-baseline gap-3 px-4 py-2.5 hover:bg-neutral-100">
                                    <span class="min-w-0 grow text-[13px] leading-snug">
                                        <span class="font-mono text-xs text-neutral-600">{{ $account->code }}</span>
                                        {{ $account->name }}
                                        @if ($account->is_auxiliary)
                                            <span class="badge ml-1 border-neutral-400 text-[9px] text-neutral-600">AUX</span>
                                        @endif
                                    </span>
                                    <span class="hidden shrink-0 text-[10px] uppercase tracking-[0.08em] text-neutral-500 sm:inline">
                                        {{ $row['side'] === 'debit' ? 'deudor' : 'acreedor' }}
                                    </span>
                                    <span class="w-[7.5rem] shrink-0 text-right font-mono text-[13px] font-extrabold sm:w-36 {{ (float) $row['balance'] < 0 ? 'text-accent-600' : '' }}">
                                        {{ $money($row['balance']) }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach

        @if ($report['count'] > 0 || $search !== '')
            <div class="border px-4 py-3 text-[13px] {{ $report['balanced'] ? 'border-ink' : 'border-accent text-accent-600' }}">
                <div class="font-extrabold">
                    Saldos deudores = saldos acreedores {{ $report['balanced'] ? '✓' : '✗' }}
                </div>
                <div class="mt-1 flex flex-wrap gap-x-6 gap-y-1 font-mono text-xs">
                    <span>Deudores {{ $money($report['debit_total']) }}</span>
                    <span>Acreedores {{ $money($report['credit_total']) }}</span>
                </div>
            </div>
            <p class="text-xs text-neutral-600">
                Saldos según la naturaleza de cada cuenta. Un importe en rojo es un saldo contrario
                a su naturaleza (por ejemplo, la depreciación acumulada o un banco en descubierto).
                Ingresos y gastos se acumulan desde el inicio de los registros hasta la fecha elegida.
            </p>
        @endif
    </div>
</div>
