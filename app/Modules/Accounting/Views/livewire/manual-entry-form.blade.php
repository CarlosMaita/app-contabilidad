<div>
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div>
                <a href="{{ route('journal.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Libro diario</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight mt-1">Asiento manual</h2>
                <p class="text-sm text-gray-500 mt-1">Asiento de doble partida sin operación de origen (ajustes, aperturas, correcciones).</p>
            </div>

            <form wire:submit="save" class="space-y-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="me-date" value="Fecha contable" />
                        <x-text-input id="me-date" type="date" class="mt-1 block w-full" wire:model="date" />
                        <x-input-error :messages="$errors->get('date')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="me-desc" value="Descripción" />
                        <x-text-input id="me-desc" class="mt-1 block w-full" wire:model="description" placeholder="Asiento de apertura" />
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>
                </div>

                @error('lines')
                    <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div>
                @enderror

                <div class="bg-white shadow-sm sm:rounded-lg p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="font-medium text-gray-800">Líneas</h3>
                        <div class="flex gap-2">
                            <x-secondary-button type="button" wire:click="addLine('debit')">+ Debe</x-secondary-button>
                            <x-secondary-button type="button" wire:click="addLine('credit')">+ Haber</x-secondary-button>
                        </div>
                    </div>

                    @foreach ($lines as $i => $line)
                        <div class="grid grid-cols-12 gap-2 items-start bg-gray-50 rounded-md p-3" wire:key="mline-{{ $i }}">
                            <div class="col-span-6 sm:col-span-2">
                                <select wire:model.live="lines.{{ $i }}.side"
                                        class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="debit">Debe</option>
                                    <option value="credit">Haber</option>
                                </select>
                            </div>
                            <div class="col-span-6 sm:col-span-4">
                                <select wire:model="lines.{{ $i }}.account_id"
                                        class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— Cuenta —</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('lines.'.$i.'.account_id')" class="mt-1" />
                            </div>
                            <div class="col-span-5 sm:col-span-2">
                                <x-text-input type="number" step="0.01" min="0" placeholder="0.00"
                                              class="block w-full text-sm text-right"
                                              wire:model.live.debounce.400ms="lines.{{ $i }}.amount" />
                                <x-input-error :messages="$errors->get('lines.'.$i.'.amount')" class="mt-1" />
                            </div>
                            <div class="col-span-5 sm:col-span-3">
                                <x-text-input placeholder="Memo (opcional)" class="block w-full text-sm"
                                              wire:model="lines.{{ $i }}.memo" />
                            </div>
                            <div class="col-span-2 sm:col-span-1 pt-1 text-right">
                                <button type="button" class="text-red-600 hover:underline text-sm"
                                        wire:click="removeLine({{ $i }})">Quitar</button>
                            </div>
                        </div>
                    @endforeach

                    <div class="flex items-center justify-between border-t pt-3 text-sm">
                        <p class="{{ $balanced ? 'text-green-700' : 'text-yellow-700' }}">
                            {{ $balanced ? '✓ El asiento balancea.' : '⚠ Debe y Haber deben ser iguales y mayores a cero.' }}
                        </p>
                        <p class="font-mono">
                            Debe: <span class="font-semibold">{{ number_format((float) $totalDebit, 2) }}</span>
                            &nbsp;·&nbsp;
                            Haber: <span class="font-semibold">{{ number_format((float) $totalCredit, 2) }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-primary-button type="submit">Registrar asiento</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
