<div>
    <x-page-header title="Mapeo contable" route="/mappings · Mappings" />

    <div class="flex flex-col gap-4 p-6">
        <p class="max-w-[70ch] text-sm text-neutral-700">
            Cada operación necesita un mapeo Debe/Haber para que sus eventos generen asientos en el diario.
        </p>

        <div class="panel overflow-x-auto">
            <table class="w-full border-collapse text-[13px]">
                <thead>
                    <tr class="border-b-2 border-ink">
                        <th class="th">Operación</th>
                        <th class="th">Mapeo</th>
                        <th class="th">Eventos sin contabilizar</th>
                        <th class="th text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr class="row-hover" wire:key="map-type-{{ $type->id }}">
                            <td class="td font-extrabold">{{ $type->name }}</td>
                            <td class="td">
                                @if ($activeMappings->has($type->id))
                                    <span class="badge border-ink text-ink">v{{ $activeMappings[$type->id]->version }} activa</span>
                                @else
                                    <span class="badge border-neutral-400 text-neutral-700">Sin mapeo</span>
                                @endif
                            </td>
                            <td class="td font-mono">{{ $type->unposted_count }}</td>
                            <td class="td text-right">
                                <a href="{{ route('mappings.edit', $type) }}" wire:navigate class="btn-ghost text-xs">
                                    {{ $activeMappings->has($type->id) ? 'Editar mapeo' : 'Configurar mapeo' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="td py-8 text-center text-neutral-700">
                                No hay operaciones definidas todavía.
                                <a href="{{ route('operations.index') }}" wire:navigate class="text-accent hover:text-accent-700">Creá una primero</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
