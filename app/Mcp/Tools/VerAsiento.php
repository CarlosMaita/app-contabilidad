<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('ver_asiento')]
#[Description('Detalle de un asiento por su número: fecha, descripción, estado, origen (operación o manual) y líneas de debe/haber.')]
#[IsReadOnly]
class VerAsiento extends AccountingTool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate(['numero' => ['required', 'integer', 'min:1']]);

        return $this->attempt(fn () => $this->respond($this->entryData(
            JournalEntry::with(['lines.account', 'execution.operationType', 'reverses'])
                ->where('number', $request->get('numero'))
                ->firstOrFail()
        )));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'numero' => $schema->integer()->description('Número correlativo del asiento.')->required(),
        ];
    }
}
