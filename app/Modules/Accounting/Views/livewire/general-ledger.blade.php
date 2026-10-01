<div>
    <x-page-header title="Libro mayor" route="/ledger · GeneralLedger" />

    <div class="flex flex-col gap-3 p-4 sm:gap-4 sm:p-6">
        @php($money = fn ($v) => number_format((float) $v, 2, ',', '.'))

        <div class="grid grid-cols-2 gap-x-3 gap-y-2 sm:flex sm:flex-wrap sm:items-end sm:gap-3">
            <div class="col-span-2 sm:min-w-[240px] sm:max-w-[300px] sm:grow">
                <x-input-label value="Cuenta" class="text-[10px]" />
                <select wire:model.live="accountId" class="wf-input mt-1">
                    <option value="">— Elegir cuenta —</option>
                    @foreach ($accounts as $a)
                        <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label value="Desde" class="text-[10px]" />
                <x-text-input type="date" class="mt-1 w-full sm:w-[140px]" wire:model.live="from" />
            </div>
            <div>
                <x-input-label value="Hasta" class="text-[10px]" />
                <x-text-input type="date" class="mt-1 w-full sm:w-[140px]" wire:model.live="to" />
            </div>
        </div>

        @if ($account === null)
            <div class="panel p-8 text-center text-neutral-700">Elegí una cuenta para ver sus movimientos.</div>
        @else
            <div class="flex items-center gap-2">
                <div class="min-w-0 grow text-sm leading-snug">
                    <span class="font-mono font-extrabold">{{ $account->code }}</span>
                    <span class="font-extrabold">{{ $account->name }}</span>
                    <span class="block text-xs text-neutral-600 sm:inline">({{ $account->type->label() }} — saldo {{ $account->type->isDebitNature() ? 'deudor' : 'acreedor' }})</span>
                </div>
                <a href="{{ route('exports.ledger', ['format' => 'pdf', 'account_id' => $account->id, 'from' => $from, 'to' => $to]) }}"
                   class="btn-secondary shrink-0 px-2.5 py-1.5 text-xs sm:px-3.5 sm:py-2 sm:text-[13px]">PDF</a>
                <a href="{{ route('exports.ledger', ['format' => 'xlsx', 'account_id' => $account->id, 'from' => $from, 'to' => $to]) }}"
                   class="btn-secondary shrink-0 px-2.5 py-1.5 text-xs sm:px-3.5 sm:py-2 sm:text-[13px]">XLSX</a>
            </div>

            @php($delta = bcsub($result['closing'], $result['opening'], 2))
            <div class="grid grid-cols-3 border-2 border-ink">
                <div class="border-r border-neutral-300 bg-white px-2.5 py-2 sm:px-4 sm:py-3.5">
                    <div class="kicker">Saldo inicial</div>
                    <div class="font-mono text-[14px] font-extrabold [overflow-wrap:anywhere] sm:text-[22px]">{{ $money($result['opening']) }}</div>
                </div>
                <div class="border-r border-neutral-300 bg-white px-2.5 py-2 sm:px-4 sm:py-3.5">
                    <div class="kicker">Movimientos</div>
                    <div class="font-mono text-[14px] font-extrabold [overflow-wrap:anywhere] sm:text-[22px]">{{ ((float) $delta >= 0 ? '+ ' : '') . $money($delta) }}</div>
                </div>
                <div class="bg-white px-2.5 py-2 sm:px-4 sm:py-3.5">
                    <div class="kicker">Saldo final</div>
                    <div class="font-mono text-[14px] font-extrabold [overflow-wrap:anywhere] sm:text-[22px]">{{ $money($result['closing']) }}</div>
                </div>
            </div>

            {{-- Móvil: lista de movimientos --}}
            <div class="panel sm:hidden">
                <ul class="divide-y divide-neutral-200">
                    @forelse ($result['movements'] as $m)
                        <li class="px-3 py-2">
                            <div class="flex items-baseline gap-3">
                                <span class="min-w-0 grow text-[13px] leading-snug">
                                    {{ $m->description }}
                                    @if ($m->memo)
                                        <span class="text-xs text-neutral-600">— {{ $m->memo }}</span>
                                    @endif
                                </span>
                                <span class="shrink-0 whitespace-nowrap font-mono text-[13px] font-extrabold">
                                    <span class="font-sans text-[10px] font-normal uppercase text-neutral-500">{{ $m->debit != 0 ? 'Debe' : 'Haber' }}</span>
                                    {{ $money($m->debit != 0 ? $m->debit : $m->credit) }}
                                </span>
                            </div>
                            <div class="mt-0.5 flex items-baseline gap-2 font-mono text-[11px] text-neutral-600">
                                <span>{{ $m->date->format('d/m/Y') }}</span>
                                <span class="font-extrabold text-ink">#{{ $m->number }}</span>
                                <span class="ml-auto whitespace-nowrap">saldo <span class="font-extrabold text-ink">{{ $money($m->balance) }}</span></span>
                            </div>
                        </li>
                    @empty
                        <li class="px-3 py-6 text-center text-[13px] text-neutral-700">Sin movimientos en el período.</li>
                    @endforelse
                </ul>
                <div class="flex items-baseline justify-between border-t-2 border-ink px-3 py-2 text-[13px] font-extrabold">
                    <span>Saldo final</span>
                    <span class="font-mono">{{ $money($result['closing']) }}</span>
                </div>
            </div>

            {{-- Escritorio: tabla --}}
            <div class="panel hidden overflow-x-auto sm:block">
                <table class="w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="border-b-2 border-ink">
                            <th class="th">Fecha</th>
                            <th class="th">Asiento</th>
                            <th class="th">Detalle</th>
                            <th class="th text-right">Debe</th>
                            <th class="th text-right">Haber</th>
                            <th class="th text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($result['movements'] as $m)
                            <tr class="row-hover">
                                <td class="td whitespace-nowrap font-mono">{{ $m->date->format('d/m/Y') }}</td>
                                <td class="td font-mono">#{{ $m->number }}</td>
                                <td class="td">
                                    {{ $m->description }}
                                    @if ($m->memo)
                                        <span class="text-xs text-neutral-600">— {{ $m->memo }}</span>
                                    @endif
                                </td>
                                <td class="td text-right font-mono">{{ $m->debit != 0 ? $money($m->debit) : '' }}</td>
                                <td class="td text-right font-mono">{{ $m->credit != 0 ? $money($m->credit) : '' }}</td>
                                <td class="td text-right font-mono font-extrabold">{{ $money($m->balance) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="td py-8 text-center text-neutral-700">Sin movimientos en el período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="border-t-2 border-ink px-4 py-2 text-right font-extrabold">Saldo final</td>
                            <td class="border-t-2 border-ink px-4 py-2 text-right font-mono font-extrabold">{{ $money($result['closing']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="text-xs text-neutral-700">
                Saldo acumulado según la naturaleza de la cuenta ({{ $account->type->isDebitNature() ? 'debe − haber' : 'haber − debe' }}).
            </div>
        @endif
    </div>
</div>
