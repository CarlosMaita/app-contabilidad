<div>
    <x-page-header title="Libros auxiliares" route="/subledgers · SubledgerBook" />

    <div class="flex flex-col gap-4 p-6">
        <p class="max-w-[70ch] text-sm text-neutral-700">
            Detalle por tercero de las cuentas con auxiliares. En el balance, el P&amp;L y el libro mayor
            solo aparece la cuenta principal consolidada; acá se revisa el movimiento de cada auxiliar.
        </p>

        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[240px] grow sm:max-w-[300px]">
                <x-input-label value="Cuenta principal" class="text-[10px]" />
                <select wire:model.live="principalId" class="wf-input mt-1">
                    <option value="">— Elegir cuenta —</option>
                    @foreach ($principals as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($principal !== null)
                <div class="min-w-[240px] grow sm:max-w-[300px]">
                    <x-input-label value="Auxiliar" class="text-[10px]" />
                    <select wire:model.live="auxiliaryId" class="wf-input mt-1">
                        <option value="">— Todos (resumen) —</option>
                        @foreach ($summary as $row)
                            <option value="{{ $row['account']->id }}">{{ $row['account']->code }} — {{ $row['account']->name }}</option>
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
            @endif
            @if ($auxiliary !== null)
                <a href="{{ route('exports.ledger', ['format' => 'pdf', 'account_id' => $auxiliary->id, 'from' => $from, 'to' => $to]) }}"
                   class="btn-secondary ml-auto">PDF</a>
                <a href="{{ route('exports.ledger', ['format' => 'xlsx', 'account_id' => $auxiliary->id, 'from' => $from, 'to' => $to]) }}"
                   class="btn-secondary">XLSX</a>
            @endif
        </div>

        @if ($principals->isEmpty())
            <div class="panel p-8 text-center text-neutral-700">
                No hay cuentas con auxiliares todavía. Creá una sub-cuenta marcada como
                «Cuenta auxiliar» desde el <a href="{{ route('accounts.index') }}" wire:navigate class="text-accent hover:text-accent-700">plan de cuentas</a>.
            </div>
        @elseif ($principal === null)
            <div class="panel p-8 text-center text-neutral-700">Elegí una cuenta principal para ver sus auxiliares.</div>
        @elseif ($auxiliary === null)
            <div class="panel overflow-x-auto">
                <table class="w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="border-b-2 border-ink">
                            <th class="th">Código</th>
                            <th class="th">Auxiliar</th>
                            <th class="th text-right">Saldo</th>
                            <th class="th text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary as $row)
                            <tr class="row-hover">
                                <td class="td font-mono">{{ $row['account']->code }}</td>
                                <td class="td">{{ $row['account']->name }}</td>
                                <td class="td text-right font-mono font-extrabold">{{ number_format((float) $row['balance'], 2, ',', '.') }}</td>
                                <td class="td text-right">
                                    <button type="button" class="btn-ghost text-xs" wire:click="$set('auxiliaryId', '{{ $row['account']->id }}')">Ver movimientos</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="border-t-2 border-ink px-4 py-2 font-extrabold">
                                Total {{ $principal->code }} {{ $principal->name }}
                            </td>
                            <td class="border-t-2 border-ink px-4 py-2 text-right font-mono font-extrabold">
                                {{ number_format((float) $principalBalance, 2, ',', '.') }}
                            </td>
                            <td class="border-t-2 border-ink"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="text-xs text-neutral-700">
                El total coincide con el saldo de la cuenta principal en el libro mayor.
            </div>
        @else
            <div class="text-sm">
                <span class="font-mono font-extrabold">{{ $auxiliary->code }}</span>
                <span class="font-extrabold">{{ $auxiliary->name }}</span>
                <span class="text-xs text-neutral-600">(auxiliar de {{ $principal->code }} {{ $principal->name }})</span>
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
                        <tr>
                            <td colspan="5" class="td italic text-neutral-700">Saldo inicial</td>
                            <td class="td text-right font-mono">{{ number_format((float) $result['opening'], 2, ',', '.') }}</td>
                        </tr>
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
        @endif
    </div>
</div>
