<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Operations\Models\OperationVariable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('tipos_de_operacion')]
#[Description('Tipos de operación definidos (ventas, cobros, pagos, etc.) con las variables que piden y si tienen mapeo contable activo. Consultalo antes de usar «ejecutar_operacion»: el payload usa los nombres de las variables.')]
#[IsReadOnly]
class TiposDeOperacion extends AccountingTool
{
    public function handle(Request $request): ResponseFactory
    {
        $mapped = AccountingMapping::where('is_active', true)->pluck('operation_type_id')->all();

        return $this->respond([
            'tipos' => OperationType::with('variables')->orderBy('name')->get()->map(fn (OperationType $type): array => [
                'codigo' => $type->code,
                'nombre' => $type->name,
                'descripcion' => $type->description,
                'activo' => $type->is_active,
                'tiene_mapeo' => in_array($type->id, $mapped, true),
                'variables' => $type->variables->map(fn (OperationVariable $v): array => [
                    'nombre' => $v->name,
                    'etiqueta' => $v->label,
                    'tipo' => $v->type->value,
                    'requerida' => $v->is_required,
                    'por_defecto' => $v->default_value,
                ])->all(),
            ]),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
