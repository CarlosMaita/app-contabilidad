<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div>
                <a href="{{ route('mappings.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Mapeos</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight mt-1">
                    Mapeo contable: {{ $operationType->name }}
                    @if ($activeVersion)
                        <span class="ml-2 align-middle inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">v{{ $activeVersion }} activa</span>
                    @endif
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Variables disponibles:
                    @forelse ($operationType->variables as $v)
                        <code class="bg-gray-100 rounded px-1">{{ $v->name }}</code>@if(!$loop->last), @endif
                    @empty
                        <em>ninguna</em>
                    @endforelse
                    — Funciones: @foreach ($allowedFunctions as $fn)<code class="bg-gray-100 rounded px-1">{{ $fn }}()</code>@if(!$loop->last), @endif @endforeach
                </p>
            </div>

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif

            @if ($pendingCount > 0 && $activeVersion)
                <div class="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 flex items-center justify-between gap-4">
                    <span>Hay {{ $pendingCount }} evento(s) de esta operación sin contabilizar.</span>
                    <x-secondary-button wire:click="processPending">Procesar eventos pendientes</x-secondary-button>
                </div>
            @endif

            <form wire:submit="save" class="space-y-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-4 space-y-2">
                    <x-input-label for="description_template" value="Plantilla de descripción del asiento (opcional)" />
                    <x-text-input id="description_template" class="block w-full"
                                  placeholder="Pago a {proveedor} por {monto}"
                                  wire:model="description_template" />
                    <p class="text-xs text-gray-500">Usá <code>{variable}</code> para interpolar valores del payload.</p>
                </div>

                @error('lines')
                    <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div>
                @enderror

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach (['debit' => 'Debe', 'credit' => 'Haber'] as $side => $sideLabel)
                        <div class="bg-white shadow-sm sm:rounded-lg p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="font-medium text-gray-800">{{ $sideLabel }}</h3>
                                <x-secondary-button type="button" wire:click="addLine('{{ $side }}')">Agregar línea</x-secondary-button>
                            </div>

                            @foreach ($lines as $i => $line)
                                @if ($line['side'] === $side)
                                    <div class="bg-gray-50 rounded-md p-3 space-y-2" wire:key="line-{{ $i }}">
                                        <div class="flex gap-2 items-start">
                                            <div class="w-32 shrink-0">
                                                <select wire:model.live="lines.{{ $i }}.target"
                                                        class="block w-full text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="fixed">Cuenta fija</option>
                                                    <option value="variable">Por variable</option>
                                                </select>
                                            </div>
                                            <div class="grow">
                                                @if ($line['target'] === 'variable')
                                                    <select wire:model="lines.{{ $i }}.account_variable"
                                                            class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <option value="">— Variable de cuenta —</option>
                                                        @foreach ($accountVariables as $av)
                                                            <option value="{{ $av->name }}">{{ $av->label }} ({{ $av->name }})</option>
                                                        @endforeach
                                                    </select>
                                                    <x-input-error :messages="$errors->get('lines.'.$i.'.account_variable')" class="mt-1" />
                                                @else
                                                    <select wire:model="lines.{{ $i }}.account_id"
                                                            class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <option value="">— Cuenta —</option>
                                                        @foreach ($accounts as $account)
                                                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <x-input-error :messages="$errors->get('lines.'.$i.'.account_id')" class="mt-1" />
                                                @endif
                                            </div>
                                            <button type="button" class="text-red-600 hover:underline text-sm pt-1"
                                                    wire:click="removeLine({{ $i }})">Quitar</button>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <x-text-input class="block w-full text-sm font-mono" placeholder="monto"
                                                              wire:model.blur="lines.{{ $i }}.amount_expression" />
                                                <x-input-error :messages="$errors->get('lines.'.$i.'.amount_expression')" class="mt-1" />
                                            </div>
                                            <x-text-input class="block w-full text-sm" placeholder="Memo (opcional, admite {variable})"
                                                          wire:model.blur="lines.{{ $i }}.memo_template" />
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-4 space-y-3">
                    <h3 class="font-medium text-gray-800">Previsualización con valores de ejemplo</h3>
                    <p class="text-xs text-gray-500 font-mono">{{ json_encode($preview['sample'], JSON_UNESCAPED_UNICODE) }}</p>

                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="px-3 py-1.5">Cuenta</th>
                                <th class="px-3 py-1.5 text-right">Debe</th>
                                <th class="px-3 py-1.5 text-right">Haber</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($preview['rows'] as $row)
                                <tr>
                                    <td class="px-3 py-1.5">
                                        {{ $row['account'] }}
                                        @if ($row['error'])
                                            <span class="block text-xs text-red-600">{{ $row['error'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ $row['side'] === 'debit' ? ($row['amount'] ?? '…') : '' }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ $row['side'] === 'credit' ? ($row['amount'] ?? '…') : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold border-t">
                                <td class="px-3 py-1.5">Totales</td>
                                <td class="px-3 py-1.5 text-right font-mono">{{ $preview['debit'] }}</td>
                                <td class="px-3 py-1.5 text-right font-mono">{{ $preview['credit'] }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    @if ($preview['balanced'])
                        <p class="text-sm text-green-700">✓ El asiento de ejemplo balancea.</p>
                    @else
                        <p class="text-sm text-yellow-700">⚠ El asiento de ejemplo no balancea (Debe {{ $preview['debit'] }} vs Haber {{ $preview['credit'] }}).</p>
                    @endif
                </div>

                <div class="flex justify-end gap-3">
                    <x-primary-button type="submit">
                        {{ $activeVersion ? 'Guardar como nueva versión' : 'Guardar mapeo' }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
