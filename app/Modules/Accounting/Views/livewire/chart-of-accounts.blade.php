<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Plan de cuentas</h2>
                <x-primary-button wire:click="create">Nueva cuenta</x-primary-button>
            </div>

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-4 space-y-4">
                <x-text-input type="search" class="w-full sm:w-80" placeholder="Buscar por código o nombre…"
                              wire:model.live.debounce.300ms="search" />

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="px-3 py-2">Código</th>
                                <th class="px-3 py-2">Nombre</th>
                                <th class="px-3 py-2">Tipo</th>
                                <th class="px-3 py-2">Imputable</th>
                                <th class="px-3 py-2">Activa</th>
                                <th class="px-3 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $row)
                                @php($account = $row['account'])
                                <tr class="{{ $account->is_active ? '' : 'opacity-50' }}">
                                    <td class="px-3 py-2 font-mono whitespace-nowrap">
                                        <span style="padding-left: {{ $row['depth'] * 1.25 }}rem"></span>{{ $account->code }}
                                    </td>
                                    <td class="px-3 py-2 {{ $row['depth'] === 0 ? 'font-semibold' : '' }}">{{ $account->name }}</td>
                                    <td class="px-3 py-2">{{ $account->type->label() }}</td>
                                    <td class="px-3 py-2">{{ $account->is_postable ? 'Sí' : '—' }}</td>
                                    <td class="px-3 py-2">{{ $account->is_active ? 'Sí' : 'No' }}</td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap space-x-2">
                                        <button type="button" class="text-indigo-600 hover:underline"
                                                wire:click="create({{ $account->id }})">Sub-cuenta</button>
                                        <button type="button" class="text-indigo-600 hover:underline"
                                                wire:click="edit({{ $account->id }})">Editar</button>
                                        <button type="button" class="text-red-600 hover:underline"
                                                wire:click="delete({{ $account->id }})"
                                                wire:confirm="¿Eliminar la cuenta {{ $account->code }} {{ $account->name }}?">Eliminar</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-500">
                                        No hay cuentas todavía. Creá la primera con «Nueva cuenta».
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
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="cancel"></div>
            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ $editingId ? 'Editar cuenta' : 'Nueva cuenta' }}
                </h3>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="code" value="Código" />
                            <x-text-input id="code" class="mt-1 block w-full" wire:model="code" placeholder="1.1.01" />
                            <x-input-error :messages="$errors->get('code')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="type" value="Tipo" />
                            <select id="type" wire:model="type" @disabled($parent_id)
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach ($types as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @if ($parent_id)
                                <p class="mt-1 text-xs text-gray-500">Hereda el tipo de la cuenta padre.</p>
                            @endif
                            <x-input-error :messages="$errors->get('type')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="name" value="Nombre" />
                        <x-text-input id="name" class="mt-1 block w-full" wire:model="name" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="parent_id" value="Cuenta padre (opcional)" />
                        <select id="parent_id" wire:model.live="parent_id"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— Sin padre (cuenta raíz) —</option>
                            @foreach ($parentOptions as $option)
                                @if ($option->id !== $editingId)
                                    <option value="{{ $option->id }}">{{ $option->code }} — {{ $option->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('parent_id')" class="mt-1" />
                    </div>

                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="is_postable"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            Imputable (recibe apuntes)
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="is_active"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            Activa
                        </label>
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
