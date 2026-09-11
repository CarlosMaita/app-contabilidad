<div>
    <x-page-header title="Ejecutar operación" route="/operations/{{ $operationType->id }}/execute · ExecuteOperationForm" />

    <div class="flex flex-col gap-4 p-6">
        <div>
            <a href="{{ route('operations.index') }}" wire:navigate class="text-xs text-accent hover:text-accent-700">← Tipos de operación</a>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="panel">
                <div class="panel-head">
                    <span class="panel-title">{{ $operationType->name }}</span>
                    <span class="ml-auto font-mono text-xs text-neutral-700">{{ $operationType->code }}</span>
                </div>
                <form wire:submit="save" class="flex flex-col gap-4 p-4">
                    @if ($operationType->description)
                        <p class="text-[13px] text-neutral-700">{{ $operationType->description }}</p>
                    @endif

                    @foreach ($operationType->variables as $variable)
                        <div wire:key="field-{{ $variable->name }}">
                            <x-input-label :for="'var-'.$variable->name">
                                {{ $variable->label }}
                                <span class="font-mono normal-case tracking-normal text-neutral-500">{{ '{'.$variable->name.'}' }} · {{ $variable->type->value }}@unless ($variable->is_required) · opcional @endunless</span>
                            </x-input-label>

                            @switch($variable->type->value)
                                @case('decimal')
                                    <x-text-input id="var-{{ $variable->name }}" type="number" step="0.01" inputmode="decimal"
                                                  class="mt-1.5" wire:model="values.{{ $variable->name }}" />
                                    @break
                                @case('integer')
                                    <x-text-input id="var-{{ $variable->name }}" type="number" step="1"
                                                  class="mt-1.5" wire:model="values.{{ $variable->name }}" />
                                    @break
                                @case('date')
                                    <x-text-input id="var-{{ $variable->name }}" type="date"
                                                  class="mt-1.5" wire:model="values.{{ $variable->name }}" />
                                    @break
                                @case('boolean')
                                    <label class="mt-2 flex items-center gap-2 text-sm text-neutral-800">
                                        <input type="checkbox" wire:model="values.{{ $variable->name }}" style="border-radius: 0;"
                                               class="border-neutral-400 text-accent focus:ring-accent">
                                        Sí
                                    </label>
                                    @break
                                @case('account')
                                    <select id="var-{{ $variable->name }}" wire:model="values.{{ $variable->name }}" class="wf-input mt-1.5">
                                        <option value="">— Elegir cuenta —</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @default
                                    <x-text-input id="var-{{ $variable->name }}" type="text"
                                                  class="mt-1.5" wire:model="values.{{ $variable->name }}" />
                            @endswitch

                            <x-input-error :messages="$errors->get('values.'.$variable->name)" class="mt-1" />
                        </div>
                    @endforeach

                    <div class="h-0.5 bg-neutral-300"></div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="executed_at" value="Fecha contable" />
                            <x-text-input id="executed_at" type="date" class="mt-1.5" wire:model="executed_at" />
                            <x-input-error :messages="$errors->get('executed_at')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="description" value="Descripción (opcional)" />
                            <x-text-input id="description" class="mt-1.5" wire:model="description" />
                        </div>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <x-primary-button type="submit">Ejecutar</x-primary-button>
                        <a href="{{ route('events.index') }}" wire:navigate class="btn-secondary">Ver log de eventos</a>
                    </div>
                </form>
            </div>

            <div class="panel h-fit">
                <div class="panel-head"><span class="panel-title">Qué pasa al ejecutar</span></div>
                <div class="flex flex-col gap-2.5 p-4 text-[13px]">
                    @foreach ([
                        ['Evento registrado', 'el payload se persiste con estado pending'],
                        ['Mapeo resuelto', 'versión activa del tipo de operación'],
                        ['Asiento generado', 'pending pasa a posted, unmapped o failed'],
                        ['Trazabilidad', 'un asiento por evento, idempotente'],
                    ] as $step)
                        <div class="grid grid-cols-[18px_minmax(0,1fr)] items-start gap-2.5">
                            <div class="mt-1 h-3 w-3 border border-ink"></div>
                            <div>
                                <div class="font-extrabold">{{ $step[0] }}</div>
                                <div class="font-mono text-[11px] text-neutral-700">{{ $step[1] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
