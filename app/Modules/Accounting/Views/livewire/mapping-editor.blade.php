<div>
    <x-page-header title="Editor de mapeo" route="/mappings/{{ $operationType->id }} · MappingEditor" />

    <div class="flex flex-col gap-4 p-6">
        <div class="flex flex-wrap items-baseline gap-3">
            <a href="{{ route('mappings.index') }}" wire:navigate class="text-xs text-accent hover:text-accent-700">← Mapeos</a>
            <span class="text-lg font-extrabold">{{ $operationType->name }}</span>
            @if ($activeVersion)
                <span class="badge border-ink text-ink">v{{ $activeVersion }} activa</span>
            @endif
            <span class="ml-auto font-mono text-xs text-neutral-700">
                variables: {{ $operationType->variables->pluck('name')->implode(', ') ?: '—' }}
                · funciones: {{ implode(', ', $allowedFunctions) }}
            </span>
        </div>

        @if (session('status'))
            <div class="note-accent text-sm">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="note-accent text-sm font-extrabold text-accent-600">{{ session('error') }}</div>
        @endif

        @if ($pendingCount > 0 && $activeVersion)
            <div class="note-accent flex flex-wrap items-center justify-between gap-3 text-sm">
                <span>Hay {{ $pendingCount }} evento(s) de esta operación sin contabilizar.</span>
                <x-secondary-button wire:click="processPending">Procesar eventos pendientes ({{ $pendingCount }})</x-secondary-button>
            </div>
        @endif

        <form wire:submit="save" class="flex flex-col gap-4">
            <div class="panel p-4">
                <x-input-label for="description_template" value="Plantilla de descripción del asiento (opcional)" />
                <x-text-input id="description_template" class="mt-1.5" placeholder="Pago a {proveedor} por {monto}"
                              wire:model="description_template" />
                <p class="mt-1 text-xs text-neutral-600">Usá <span class="font-mono">{variable}</span> para interpolar valores del payload.</p>
            </div>

            @error('lines')
                <div class="note-accent text-sm font-extrabold text-accent-600">{{ $message }}</div>
            @enderror

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                @foreach (['debit' => 'Debe', 'credit' => 'Haber'] as $side => $sideLabel)
                    <div class="panel min-w-0">
                        <div class="panel-head">
                            <span class="panel-title">{{ $sideLabel }}</span>
                            <x-secondary-button class="ml-auto" wire:click="addLine('{{ $side }}')">Agregar línea</x-secondary-button>
                        </div>

                        @foreach ($lines as $i => $line)
                            @if ($line['side'] === $side)
                                <div class="flex flex-col gap-2 border-b border-neutral-200 px-4 py-3" wire:key="line-{{ $i }}">
                                    <div class="flex items-start gap-2">
                                        <div class="w-32 shrink-0">
                                            <select wire:model.live="lines.{{ $i }}.target" class="wf-input text-xs">
                                                <option value="fixed">Cuenta fija</option>
                                                <option value="variable">Por variable</option>
                                            </select>
                                        </div>
                                        <div class="grow">
                                            @if ($line['target'] === 'variable')
                                                <select wire:model="lines.{{ $i }}.account_variable" class="wf-input">
                                                    <option value="">— Variable de cuenta —</option>
                                                    @foreach ($accountVariables as $av)
                                                        <option value="{{ $av->name }}">{{ $av->label }} ({{ $av->name }})</option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('lines.'.$i.'.account_variable')" class="mt-1" />
                                            @else
                                                <select wire:model="lines.{{ $i }}.account_id" class="wf-input">
                                                    <option value="">— Cuenta —</option>
                                                    @foreach ($accounts as $account)
                                                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('lines.'.$i.'.account_id')" class="mt-1" />
                                            @endif
                                        </div>
                                        <button type="button" class="btn-ghost pt-1.5 text-xs" wire:click="removeLine({{ $i }})">Quitar</button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <x-text-input class="font-mono text-[13px]" placeholder="monto"
                                                          wire:model.blur="lines.{{ $i }}.amount_expression" />
                                            <x-input-error :messages="$errors->get('lines.'.$i.'.amount_expression')" class="mt-1" />
                                        </div>
                                        <x-text-input class="text-[13px]" placeholder="Memo (admite {variable})"
                                                      wire:model.blur="lines.{{ $i }}.memo_template" />
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="panel">
                <div class="panel-head">
                    <span class="panel-title">Previsualización</span>
                    <span class="font-mono text-[11px] text-neutral-700">{{ json_encode($preview['sample'], JSON_UNESCAPED_UNICODE) }}</span>
                </div>

                <table class="w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="border-b border-neutral-300">
                            <th class="th">Cuenta</th>
                            <th class="th text-right">Debe</th>
                            <th class="th text-right">Haber</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview['rows'] as $row)
                            <tr>
                                <td class="td">
                                    {{ $row['account'] }}
                                    @if ($row['error'])
                                        <span class="block text-xs font-extrabold text-accent-600">{{ $row['error'] }}</span>
                                    @endif
                                </td>
                                <td class="td text-right font-mono">{{ $row['side'] === 'debit' ? ($row['amount'] ?? '…') : '' }}</td>
                                <td class="td text-right font-mono">{{ $row['side'] === 'credit' ? ($row['amount'] ?? '…') : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="border-t-2 border-ink px-4 py-2 font-extrabold">Totales</td>
                            <td class="border-t-2 border-ink px-4 py-2 text-right font-mono font-extrabold">{{ $preview['debit'] }}</td>
                            <td class="border-t-2 border-ink px-4 py-2 text-right font-mono font-extrabold">{{ $preview['credit'] }}</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                    <span class="text-[13px] font-extrabold {{ $preview['balanced'] ? 'text-ink' : 'text-accent-600' }}">
                        {{ $preview['balanced'] ? 'El asiento de ejemplo balancea.' : 'El asiento de ejemplo no balancea (Debe '.$preview['debit'].' vs Haber '.$preview['credit'].').' }}
                    </span>
                    <x-primary-button type="submit" class="ml-auto">
                        {{ $activeVersion ? 'Guardar como nueva versión' : 'Guardar mapeo' }}
                    </x-primary-button>
                </div>
            </div>
        </form>

        <div class="text-xs text-neutral-700">
            Guardar crea una versión nueva; los asientos existentes no se modifican. Regenerar = reversión + nueva generación, nunca borrado.
        </div>
    </div>
</div>
