<div>
    <x-page-header title="Plan de cuentas" route="/accounts · ChartOfAccounts" />

    <div class="flex flex-col gap-4 p-6">
        @if (session('status'))
            <div class="note-accent text-sm">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="note-accent text-sm font-extrabold text-accent-600">{{ session('error') }}</div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <x-text-input type="search" class="max-w-[280px]" placeholder="Buscar por código o nombre"
                          wire:model.live.debounce.300ms="search" />
            <x-primary-button type="button" class="ml-auto" wire:click="create">Nueva cuenta</x-primary-button>
        </div>

        <div class="panel overflow-x-auto">
            <table class="w-full border-collapse text-[13px]">
                <thead>
                    <tr class="border-b-2 border-ink">
                        <th class="th">Código</th>
                        <th class="th">Nombre</th>
                        <th class="th">Tipo</th>
                        <th class="th">Imputable</th>
                        <th class="th">Activa</th>
                        <th class="th text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php($account = $row['account'])
                        <tr class="row-hover {{ $account->is_active ? '' : 'opacity-50' }}">
                            <td class="td whitespace-nowrap font-mono" style="padding-left: {{ 16 + $row['depth'] * 18 }}px">{{ $account->code }}</td>
                            <td class="td {{ $row['depth'] === 0 ? 'font-extrabold' : '' }}">{{ $account->name }}</td>
                            <td class="td text-neutral-700">{{ $account->type->label() }}</td>
                            <td class="td text-neutral-700">{{ $account->is_postable ? 'Sí' : 'No' }}</td>
                            <td class="td text-neutral-700">{{ $account->is_active ? 'Sí' : 'No' }}</td>
                            <td class="td whitespace-nowrap text-right">
                                <button type="button" class="btn-ghost text-xs" wire:click="create({{ $account->id }})">Sub-cuenta</button>
                                <button type="button" class="btn-ghost text-xs" wire:click="edit({{ $account->id }})">Editar</button>
                                <button type="button" class="btn-ghost text-xs" wire:click="delete({{ $account->id }})"
                                        wire:confirm="¿Eliminar la cuenta {{ $account->code }} {{ $account->name }}?">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="td py-8 text-center text-neutral-700">
                                No hay cuentas todavía. Creá la primera con «Nueva cuenta».
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="text-xs text-neutral-700">
            Jerarquía por cuenta padre. Solo las hojas imputables reciben apuntes y aparecen en los selects del mapeo.
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-neutral-900/50" wire:click="cancel"></div>
            <div class="panel relative mx-4 w-full max-w-lg shadow-2xl">
                <div class="panel-head">
                    <span class="text-lg font-extrabold">{{ $editingId ? 'Editar cuenta' : 'Nueva cuenta' }}</span>
                </div>

                <form wire:submit="save" class="flex flex-col gap-4 p-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="code" value="Código" />
                            <x-text-input id="code" class="mt-1.5 font-mono" wire:model="code" placeholder="1.1.01" />
                            <x-input-error :messages="$errors->get('code')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="type" value="Tipo" />
                            <select id="type" wire:model="type" @disabled($parent_id) class="wf-input mt-1.5">
                                @foreach ($types as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @if ($parent_id)
                                <p class="mt-1 text-xs text-neutral-600">Hereda el tipo de la cuenta padre.</p>
                            @endif
                            <x-input-error :messages="$errors->get('type')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="name" value="Nombre" />
                        <x-text-input id="name" class="mt-1.5" wire:model="name" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="parent_id" value="Cuenta padre (opcional)" />
                        <select id="parent_id" wire:model.live="parent_id" class="wf-input mt-1.5">
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
                        <label class="flex items-center gap-2 text-sm text-neutral-800">
                            <input type="checkbox" wire:model="is_postable" style="border-radius: 0;"
                                   class="border-neutral-400 text-accent focus:ring-accent">
                            Imputable (recibe apuntes)
                        </label>
                        <label class="flex items-center gap-2 text-sm text-neutral-800">
                            <input type="checkbox" wire:model="is_active" style="border-radius: 0;"
                                   class="border-neutral-400 text-accent focus:ring-accent">
                            Activa
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-neutral-300 pt-4">
                        <x-secondary-button wire:click="cancel">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
