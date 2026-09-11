<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Log de eventos</h2>

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-4 space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <x-input-label value="Operación" class="text-xs" />
                        <select wire:model.live="typeId"
                                class="mt-1 block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Todas</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Estado" class="text-xs" />
                        <select wire:model.live="status"
                                class="mt-1 block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Todos</option>
                            @foreach ($statuses as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Desde" class="text-xs" />
                        <x-text-input type="date" class="mt-1 block w-full text-sm" wire:model.live="from" />
                    </div>
                    <div>
                        <x-input-label value="Hasta" class="text-xs" />
                        <x-text-input type="date" class="mt-1 block w-full text-sm" wire:model.live="to" />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2">Operación</th>
                                <th class="px-3 py-2">Descripción</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2 text-right">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($executions as $execution)
                                <tr wire:key="exec-{{ $execution->id }}">
                                    <td class="px-3 py-2 text-gray-500">{{ $execution->id }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $execution->executed_at->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2 font-medium">{{ $execution->operationType->name }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $execution->description ?: '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $execution->status->badgeClasses() }}">
                                            {{ $execution->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap space-x-2">
                                        <button type="button" class="text-indigo-600 hover:underline"
                                                wire:click="toggleExpand({{ $execution->id }})">
                                            {{ $expandedId === $execution->id ? 'Ocultar' : 'Ver' }}
                                        </button>
                                        @if ($execution->status !== \App\Modules\Operations\Enums\ExecutionStatus::Voided)
                                            <button type="button" class="text-red-600 hover:underline"
                                                    wire:click="void({{ $execution->id }})"
                                                    wire:confirm="¿Anular esta ejecución? Si tiene asiento, se genera un contra-asiento.">
                                                Anular
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @if ($expandedId === $execution->id)
                                    <tr class="bg-gray-50">
                                        <td colspan="6" class="px-3 py-3">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                                <div>
                                                    <p class="font-medium text-gray-700 mb-1">Payload</p>
                                                    <pre class="bg-white border rounded p-2 overflow-x-auto">{{ json_encode($execution->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                </div>
                                                <div class="space-y-1 text-gray-600">
                                                    <p><span class="font-medium text-gray-700">Registrado:</span> {{ $execution->created_at->format('d/m/Y H:i') }}</p>
                                                    @if ($execution->error_message)
                                                        <p class="text-red-600"><span class="font-medium">Error:</span> {{ $execution->error_message }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-500">
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
    </div>
</div>
