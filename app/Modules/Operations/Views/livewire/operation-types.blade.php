<div>
    <x-page-header title="Tipos de operación" route="/operations · OperationTypes" />

    <div class="flex flex-col gap-4 p-6">
        @if (session('status'))
            <div class="note-accent text-sm">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="note-accent text-sm font-extrabold text-accent-600">{{ session('error') }}</div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <div class="text-sm text-neutral-700">Definí operaciones con variables tipadas; cada ejecución emite un evento.</div>
            <x-primary-button type="button" class="ml-auto" wire:click="create">Nueva operación</x-primary-button>
        </div>

        <div class="panel overflow-x-auto">
            <table class="w-full border-collapse text-[13px]">
                <thead>
                    <tr class="border-b-2 border-ink">
                        <th class="th">Nombre</th>
                        <th class="th">Código</th>
                        <th class="th">Variables</th>
                        <th class="th">Ejecuciones</th>
                        <th class="th">Activa</th>
                        <th class="th text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr class="row-hover {{ $type->is_active ? '' : 'opacity-50' }}">
                            <td class="td font-extrabold">{{ $type->name }}</td>
                            <td class="td font-mono text-xs">{{ $type->code }}</td>
                            <td class="td font-mono">{{ $type->variables_count }}</td>
                            <td class="td font-mono">{{ $type->executions_count }}</td>
                            <td class="td text-neutral-700">{{ $type->is_active ? 'Sí' : 'No' }}</td>
                            <td class="td whitespace-nowrap text-right">
                                @if ($type->is_active)
                                    <a href="{{ route('operations.execute', $type) }}" wire:navigate class="btn-ghost text-xs">Ejecutar</a>
                                @endif
                                <button type="button" class="btn-ghost text-xs" wire:click="edit({{ $type->id }})">Editar</button>
                                <button type="button" class="btn-ghost text-xs" wire:click="delete({{ $type->id }})"
                                        wire:confirm="¿Eliminar la operación «{{ $type->name }}»?">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="td py-8 text-center text-neutral-700">
                                No hay operaciones definidas. Creá la primera con «Nueva operación».
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="text-xs text-neutral-700">
            Nombre de variable en <span class="font-mono">snake_case</span>, único por operación.
            El tipo <span class="font-mono">account</span> genera un select del plan de cuentas en tiempo de ejecución.
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto py-8">
            <div class="fixed inset-0 bg-neutral-900/50" wire:click="cancel"></div>
            <div class="panel relative mx-4 w-full max-w-3xl shadow-2xl">
                <div class="panel-head">
                    <span class="text-lg font-extrabold">{{ $editingId ? 'Editar operación' : 'Nueva operación' }}</span>
                </div>

                <form wire:submit="save" class="flex flex-col gap-4 p-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="op-name" value="Nombre" />
                            <x-text-input id="op-name" class="mt-1.5" wire:model="name" placeholder="Pago a proveedor" />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="op-code" value="Código (slug, se genera solo)" />
                            <x-text-input id="op-code" class="mt-1.5 font-mono" wire:model="code" placeholder="pago_proveedor" />
                            <x-input-error :messages="$errors->get('code')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="op-desc" value="Descripción (opcional)" />
                        <x-text-input id="op-desc" class="mt-1.5" wire:model="description" placeholder="Para qué sirve esta operación" />
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-neutral-800">
                        <input type="checkbox" wire:model="is_active" style="border-radius: 0;"
                               class="border-neutral-400 text-accent focus:ring-accent">
                        Activa
                    </label>

                    <div class="border-t-2 border-ink pt-4">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-sm font-extrabold">Variables</span>
                            <x-secondary-button wire:click="addVariable">Agregar variable</x-secondary-button>
                        </div>

                        @error('variables')
                            <p class="mb-2 text-sm font-extrabold text-accent-600">{{ $message }}</p>
                        @enderror

                        <div class="flex flex-col gap-2">
                            @foreach ($variables as $i => $variable)
                                <div class="grid grid-cols-12 items-start gap-2 border border-neutral-300 bg-neutral-100 p-3" wire:key="var-{{ $i }}">
                                    <div class="col-span-6 sm:col-span-3">
                                        <x-input-label value="Nombre" class="text-[10px]" />
                                        <x-text-input class="mt-1 font-mono text-[13px]" placeholder="monto"
                                                      wire:model="variables.{{ $i }}.name" />
                                        <x-input-error :messages="$errors->get('variables.'.$i.'.name')" class="mt-1" />
                                    </div>
                                    <div class="col-span-6 sm:col-span-3">
                                        <x-input-label value="Etiqueta" class="text-[10px]" />
                                        <x-text-input class="mt-1 text-[13px]" placeholder="Monto"
                                                      wire:model="variables.{{ $i }}.label" />
                                        <x-input-error :messages="$errors->get('variables.'.$i.'.label')" class="mt-1" />
                                    </div>
                                    <div class="col-span-6 sm:col-span-2">
                                        <x-input-label value="Tipo" class="text-[10px]" />
                                        <select wire:model="variables.{{ $i }}.type" class="wf-input mt-1">
                                            @foreach ($variableTypes as $vt)
                                                <option value="{{ $vt->value }}">{{ $vt->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-span-6 sm:col-span-2">
                                        <x-input-label value="Valor por defecto" class="text-[10px]" />
                                        <x-text-input class="mt-1 text-[13px]" wire:model="variables.{{ $i }}.default_value" />
                                    </div>
                                    <div class="col-span-8 pt-5 sm:col-span-1">
                                        <label class="flex items-center gap-1.5 text-xs text-neutral-800">
                                            <input type="checkbox" wire:model="variables.{{ $i }}.is_required" style="border-radius: 0;"
                                                   class="border-neutral-400 text-accent focus:ring-accent">
                                            Oblig.
                                        </label>
                                    </div>
                                    <div class="col-span-4 pt-4 text-right sm:col-span-1">
                                        <button type="button" class="btn-ghost text-xs" wire:click="removeVariable({{ $i }})">Quitar</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
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
