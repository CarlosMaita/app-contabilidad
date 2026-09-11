<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>

            @if ($attention > 0)
                <a href="{{ route('events.index') }}" wire:navigate
                   class="block rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 hover:bg-yellow-100">
                    ⚠ Hay {{ $attention }} evento(s) sin contabilizar (pendientes, sin mapeo o con error). Ver el log de eventos →
                </a>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <p class="text-xs uppercase text-gray-500">Activo total (hoy)</p>
                    <p class="mt-1 text-2xl font-semibold font-mono text-gray-800">{{ number_format((float) $assets, 2) }}</p>
                    <p class="mt-1 text-xs {{ $balanced ? 'text-green-600' : 'text-red-600' }}">
                        {{ $balanced ? '✓ El balance cuadra' : '✗ El balance no cuadra' }}
                    </p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <p class="text-xs uppercase text-gray-500">Ingresos del mes</p>
                    <p class="mt-1 text-2xl font-semibold font-mono text-gray-800">{{ number_format((float) $monthIncome, 2) }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <p class="text-xs uppercase text-gray-500">Gastos del mes</p>
                    <p class="mt-1 text-2xl font-semibold font-mono text-gray-800">{{ number_format((float) $monthExpense, 2) }}</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <p class="text-xs uppercase text-gray-500">Resultado del mes</p>
                    <p class="mt-1 text-2xl font-semibold font-mono {{ (float) $monthResult >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ number_format((float) $monthResult, 2) }}
                    </p>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-gray-800">Últimos asientos</h3>
                    <a href="{{ route('journal.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Ver el diario →</a>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($lastEntries as $entry)
                            <tr>
                                <td class="px-2 py-2 font-mono text-gray-500">#{{ $entry->number }}</td>
                                <td class="px-2 py-2 whitespace-nowrap">{{ $entry->date->format('d/m/Y') }}</td>
                                <td class="px-2 py-2">{{ $entry->description }}</td>
                                <td class="px-2 py-2 text-gray-500">{{ $entry->execution?->operationType?->name ?? 'Manual' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-2 py-6 text-center text-gray-500" colspan="4">
                                    Todavía no hay asientos.
                                    <a href="{{ route('operations.index') }}" wire:navigate class="text-indigo-600 hover:underline">Ejecutá tu primera operación</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
