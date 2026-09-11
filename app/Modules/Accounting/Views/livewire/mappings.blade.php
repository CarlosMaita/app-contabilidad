<div>
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mapeos contables</h2>
            <p class="text-sm text-gray-500">
                Cada operación necesita un mapeo Debe/Haber para que sus eventos generen asientos en el diario.
            </p>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="px-3 py-2">Operación</th>
                                <th class="px-3 py-2">Mapeo</th>
                                <th class="px-3 py-2">Eventos sin contabilizar</th>
                                <th class="px-3 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($types as $type)
                                <tr wire:key="map-type-{{ $type->id }}">
                                    <td class="px-3 py-2 font-medium">{{ $type->name }}</td>
                                    <td class="px-3 py-2">
                                        @if ($activeMappings->has($type->id))
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                                                v{{ $activeMappings[$type->id]->version }} activa
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                Sin mapeo
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">{{ $type->unposted_count }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('mappings.edit', $type) }}" wire:navigate
                                           class="text-indigo-600 hover:underline">
                                            {{ $activeMappings->has($type->id) ? 'Editar mapeo' : 'Configurar mapeo' }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-8 text-center text-gray-500">
                                        No hay operaciones definidas todavía.
                                        <a href="{{ route('operations.index') }}" wire:navigate class="text-indigo-600 hover:underline">Creá una primero</a>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
