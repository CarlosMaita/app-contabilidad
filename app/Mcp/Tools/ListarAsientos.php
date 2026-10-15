<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('listar_asientos')]
#[Description('Libro diario: asientos con sus líneas, del más reciente al más antiguo. Filtra por rango de fechas y por texto (número, descripción, cuenta u operación). Paginado.')]
#[IsReadOnly]
class ListarAsientos extends AccountingTool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = JournalEntry::with(['lines.account', 'execution.operationType', 'reverses'])
            ->filtered($validated['buscar'] ?? '', $validated['desde'] ?? '', $validated['hasta'] ?? '')
            ->orderByDesc('date')
            ->orderByDesc('number')
            ->paginate($validated['por_pagina'] ?? 25, page: $validated['pagina'] ?? 1);

        return $this->respond([
            'asientos' => $page->getCollection()->map(fn (JournalEntry $e): array => $this->entryData($e)),
            'pagina' => $page->currentPage(),
            'ultima_pagina' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'desde' => $schema->string()->description('Fecha inicial AAAA-MM-DD (opcional).'),
            'hasta' => $schema->string()->description('Fecha final AAAA-MM-DD (opcional).'),
            'buscar' => $schema->string()->description('Número, descripción, código/nombre de cuenta u operación (opcional).'),
            'pagina' => $schema->integer()->description('Página (por defecto 1).'),
            'por_pagina' => $schema->integer()->description('Asientos por página, máximo 100 (por defecto 25).'),
        ];
    }
}
