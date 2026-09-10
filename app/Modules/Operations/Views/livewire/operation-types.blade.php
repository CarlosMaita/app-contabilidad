<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operaciones</h2>
                <x-primary-button wire:click="create">Nueva operación</x-primary-button>
            </div>

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="px-3 py-2">Nombre</th>
                                <th class="px-3 py-2">Código</th>
                                <th class="px-3 py-2">Variables</th>
                                <th class="px-3 py-2">Ejecuciones</th>
                                <th class="px-3 py-2">Activa</th>
                                <th class="px-3 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($types as $type)
                                <tr class="{{ $type->is_active ? '' : 'opacity-50' }}">
                                    <td class="px-3 py-2 font-medium">{{ $type->name }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $type->code }}</td>
                                    <td class="px-3 py-2">{{ $type->variables_count }}</td>
                                    <td class="px-3 py-2">{{ $type->executions_count }}</td>
                                    <td class="px-3 py-2">{{ $type->is_active ? 'Sí' : 'No' }}</td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap space-x-2">
                                        @if ($type->is_active)
                                            <a href="{{ route('operations.execute', $type) }}" wire:navigate
                                               class="text-green-700 hover:underline font-medium">Ejecutar</a>
                                        @endif
                                        <button type="button" class="text-indigo-600 hover:underline"
                                                wire:click="edit({{ $type->id }})">Editar</button>
                                        <button type="button" class="text-red-600 hover:underline"
                                                wire:click="delete({{ $type->id }})"
                                                wire:confirm="¿Eliminar la operación «{{ $type->name }}»?">Eliminar</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-500">
                                        No hay operaciones definidas. Creá la primera con «Nueva operación».
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto py-8">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="cancel"></div>
            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4 p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ $editingId ? 'Editar operación' : 'Nueva operación' }}
                </h3>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="op-name" value="Nombre" />
                            <x-text-input id="op-name" class="mt-1 block w-full" wire:model="name" placeholder="Pago a proveedor" />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="op-code" value="Código (se genera solo si lo dejás vacío)" />
                            <x-text-input id="op-code" class="mt-1 block w-full font-mono" wire:model="code" placeholder="pago_proveedor" />
                            <x-input-error :messages="$errors->get('code')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="op-desc" value="Descripción (opcional)" />
                        <x-text-input id="op-desc" class="mt-1 block w-full" wire:model="description" />
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="is_active"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        Activa
                    </label>

                    <div class="border-t pt-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-medium text-gray-800">Variables</h4>
                            <x-secondary-button type="button" wire:click="addVariable">Agregar variable</x-secondary-button>
                        </div>

                        @error('variables')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        @foreach ($variables as $i => $variable)
                            <div class="grid grid-cols-12 gap-2 items-start bg-gray-50 rounded-md p-3" wire:key="var-{{ $i }}">
                                <div class="col-span-6 sm:col-span-3">
                                    <x-input-label value="Nombre" class="text-xs" />
                                    <x-text-input class="mt-1 block w-full text-sm font-mono" placeholder="monto"
                                                  wire:model="variables.{{ $i }}.name" />
                                    <x-input-error :messages="$errors->get('variables.'.$i.'.name')" class="mt-1" />
                                </div>
                                <div class="col-span-6 sm:col-span-3">
                                    <x-input-label value="Etiqueta" class="text-xs" />
                                    <x-text-input class="mt-1 block w-full text-sm" placeholder="Monto"
                                                  wire:model="variables.{{ $i }}.label" />
                                    <x-input-error :messages="$errors->get('variables.'.$i.'.label')" class="mt-1" />
                                </div>
                                <div class="col-span-6 sm:col-span-2">
                                    <x-input-label value="Tipo" class="text-xs" />
                                    <select wire:model="variables.{{ $i }}.type"
                                            class="mt-1 block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        @foreach ($variableTypes as $vt)
                                            <option value="{{ $vt->value }}">{{ $vt->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-6 sm:col-span-2">
                                    <x-input-label value="Valor por defecto" class="text-xs" />
                                    <x-text-input class="mt-1 block w-full text-sm"
                                                  wire:model="variables.{{ $i }}.default_value" />
                                </div>
                                <div class="col-span-8 sm:col-span-1 pt-6">
                                    <label class="flex items-center gap-1 text-xs text-gray-700">
                                        <input type="checkbox" wire:model="variables.{{ $i }}.is_required"
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        Oblig.
                                    </label>
                                </div>
                                <div class="col-span-4 sm:col-span-1 pt-5 text-right">
                                    <button type="button" class="text-red-600 hover:underline text-sm"
                                            wire:click="removeVariable({{ $i }})">Quitar</button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" wire:click="cancel">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
