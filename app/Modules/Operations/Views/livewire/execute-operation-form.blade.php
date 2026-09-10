<div>
    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div>
                <a href="{{ route('operations.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Operaciones</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight mt-1">
                    Ejecutar: {{ $operationType->name }}
                </h2>
                @if ($operationType->description)
                    <p class="text-sm text-gray-500 mt-1">{{ $operationType->description }}</p>
                @endif
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="executed_at" value="Fecha contable" />
                            <x-text-input id="executed_at" type="date" class="mt-1 block w-full" wire:model="executed_at" />
                            <x-input-error :messages="$errors->get('executed_at')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="description" value="Descripción (opcional)" />
                            <x-text-input id="description" class="mt-1 block w-full" wire:model="description" />
                        </div>
                    </div>

                    @foreach ($operationType->variables as $variable)
                        <div wire:key="field-{{ $variable->name }}">
                            <x-input-label :for="'var-'.$variable->name">
                                {{ $variable->label }}
                                @unless ($variable->is_required)
                                    <span class="text-gray-400 font-normal">(opcional)</span>
                                @endunless
                            </x-input-label>

                            @switch($variable->type->value)
                                @case('decimal')
                                    <x-text-input id="var-{{ $variable->name }}" type="number" step="0.01" inputmode="decimal"
                                                  class="mt-1 block w-full" wire:model="values.{{ $variable->name }}" />
                                    @break
                                @case('integer')
                                    <x-text-input id="var-{{ $variable->name }}" type="number" step="1"
                                                  class="mt-1 block w-full" wire:model="values.{{ $variable->name }}" />
                                    @break
                                @case('date')
                                    <x-text-input id="var-{{ $variable->name }}" type="date"
                                                  class="mt-1 block w-full" wire:model="values.{{ $variable->name }}" />
                                    @break
                                @case('boolean')
                                    <label class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" wire:model="values.{{ $variable->name }}"
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        Sí
                                    </label>
                                    @break
                                @case('account')
                                    <select id="var-{{ $variable->name }}" wire:model="values.{{ $variable->name }}"
                                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        <option value="">— Elegir cuenta —</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @default
                                    <x-text-input id="var-{{ $variable->name }}" type="text"
                                                  class="mt-1 block w-full" wire:model="values.{{ $variable->name }}" />
                            @endswitch

                            <x-input-error :messages="$errors->get('values.'.$variable->name)" class="mt-1" />
                        </div>
                    @endforeach

                    <div class="flex justify-end gap-3 pt-2">
                        <x-primary-button type="submit">Registrar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
