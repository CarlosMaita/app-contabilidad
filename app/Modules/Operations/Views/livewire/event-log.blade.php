<div>
    <x-page-header title="Log de eventos" route="/events · EventLog" />

    <div class="flex flex-col gap-4 p-6">
        @if (session('status'))
            <div class="note-accent text-sm">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="note-accent text-sm font-extrabold text-accent-600">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div>
                <x-input-label value="Operación" class="text-[10px]" />
                <select wire:model.live="typeId" class="wf-input mt-1">
                    <option value="">Todas</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label value="Estado" class="text-[10px]" />
                <select wire:model.live="status" class="wf-input mt-1">
                    <option value="">Todos</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label value="Desde" class="text-[10px]" />
                <x-text-input type="date" class="mt-1" wire:model.live="from" />
            </div>
            <div>
                <x-input-label value="Hasta" class="text-[10px]" />
                <x-text-input type="date" class="mt-1" wire:model.live="to" />
            </div>
        </div>

        <div class="panel overflow-x-auto">
            <table class="w-full border-collapse text-[13px]">
                <thead>
                    <tr class="border-b-2 border-ink">
                        <th class="th">#</th>
                        <th class="th">Fecha</th>
                        <th class="th">Operación</th>
                        <th class="th">Descripción</th>
                        <th class="th">Estado</th>
                        <th class="th text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($executions as $execution)
                        <tr class="row-hover" wire:key="exec-{{ $execution->id }}">
                            <td class="td font-mono text-neutral-600">{{ $execution->id }}</td>
                            <td class="td whitespace-nowrap font-mono">{{ $execution->executed_at->format('d/m/Y') }}</td>
                            <td class="td font-extrabold">{{ $execution->operationType->name }}</td>
                            <td class="td text-neutral-700">{{ $execution->description ?: '—' }}</td>
                            <td class="td">
                                <span class="badge {{ $execution->status->badgeClasses() }}">{{ $execution->status->label() }}</span>
                            </td>
                            <td class="td whitespace-nowrap text-right">
                                <button type="button" class="btn-ghost text-xs" wire:click="toggleExpand({{ $execution->id }})">
                                    {{ $expandedId === $execution->id ? 'Ocultar' : 'Ver' }}
                                </button>
                                @if ($execution->status !== \App\Modules\Operations\Enums\ExecutionStatus::Voided)
                                    <button type="button" class="btn-ghost text-xs" wire:click="void({{ $execution->id }})"
                                            wire:confirm="¿Anular esta ejecución? Si tiene asiento, se genera un contra-asiento.">
                                        Anular
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @if ($expandedId === $execution->id)
                            <tr class="bg-neutral-100">
                                <td colspan="6" class="border-b border-neutral-300 px-4 py-3">
                                    <div class="grid grid-cols-1 gap-4 text-xs sm:grid-cols-2">
                                        <div>
                                            <p class="kicker mb-1">Payload</p>
                                            <pre class="overflow-x-auto border border-neutral-300 bg-white p-2 font-mono">{{ json_encode($execution->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                        <div class="space-y-1 text-neutral-700">
                                            <p><span class="font-extrabold text-ink">Registrado:</span> {{ $execution->created_at->format('d/m/Y H:i') }}</p>
                                            @if ($execution->error_message)
                                                <p class="font-extrabold text-accent-600">{{ $execution->error_message }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="td py-8 text-center text-neutral-700">
                                No hay eventos registrados con estos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $executions->links() }}
    </div>
</div>
