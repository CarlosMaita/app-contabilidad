<div>
    <x-page-header title="Libro mayor" route="/ledger · GeneralLedger" />

    <div class="flex flex-col gap-4 p-6">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[240px] sm:max-w-[300px] grow">
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
                <x-text-input type="date" class="mt-1 w-[140px]" wire:model.live="from" />
            </div>
            <div>
                <x-input-label value="Hasta" class="text-[10px]" />
                <x-text-input type="date" class="mt-1 w-[140px]" wire:model.live="to" />
            </div>
            @if ($account !== null)
                <a href="{{ route('exports.ledger', ['format' => 'pdf', 'account_id' => $account->id, 'from' => $from, 'to' => $to]) }}"
                   class="btn-secondary ml-auto">PDF</a>
                <a href="{{ route('exports.ledger', ['format' => 'xlsx', 'account_id' => $account->id, 'from' => $from, 'to' => $to]) }}"
                   class="btn-secondary">XLSX</a>
            @endif
        </div>

        @if ($account === null)
            <div class="panel p-8 text-center text-neutral-700">Elegí una cuenta para ver sus movimientos.</div>
        @else
            <div class="text-sm">
                <span class="font-mono font-extrabold">{{ $account->code }}</span>
                <span class="font-extrabold">{{ $account->name }}</span>
                <span class="text-xs text-neutral-600">({{ $account->type->label() }} — saldo {{ $account->type->isDebitNature() ? 'deudor' : 'acreedor' }})</span>
            </div>

            <div class="grid grid-cols-1 border-2 border-ink sm:grid-cols-3">
                <div class="border-b border-neutral-300 bg-white px-4 py-3.5 sm:border-b-0 sm:border-r">
                    <div class="kicker">Saldo inicial</div>
                    <div class="font-mono text-[22px] font-extrabold">{{ number_format((float) $result['opening'], 2, ',', '.') }}</div>
                </div>
                <div class="border-b border-neutral-300 bg-white px-4 py-3.5 sm:border-b-0 sm:border-r">
                    <div class="kicker">Movimientos</div>
                    @php($delta = bcsub($result['closing'], $result['opening'], 2))
                    <div class="font-mono text-[22px] font-extrabold">{{ ((float) $delta >= 0 ? '+ ' : '') . number_format((float) $delta, 2, ',', '.') }}</div>
                </div>
                <div class="bg-white px-4 py-3.5">
                    <div class="kicker">Saldo final</div>
                    <div class="font-mono text-[22px] font-extrabold">{{ number_format((float) $result['closing'], 2, ',', '.') }}</div>
                </div>
            </div>

            <div class="panel overflow-x-auto">
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
                                <td class="td text-right font-mono">{{ $m->debit != 0 ? number_format((float) $m->debit, 2, ',', '.') : '' }}</td>
                                <td class="td text-right font-mono">{{ $m->credit != 0 ? number_format((float) $m->credit, 2, ',', '.') : '' }}</td>
                                <td class="td text-right font-mono font-extrabold">{{ number_format((float) $m->balance, 2, ',', '.') }}</td>
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
                            <td class="border-t-2 border-ink px-4 py-2 text-right font-mono font-extrabold">{{ number_format((float) $result['closing'], 2, ',', '.') }}</td>
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
