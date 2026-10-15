<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Models\Account;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('plan_de_cuentas')]
#[Description('Lista el plan de cuentas ordenado por código: tipo, cuenta padre, si es imputable (recibe asientos), si es auxiliar y si está activa. Usalo para elegir las cuentas antes de registrar asientos.')]
#[IsReadOnly]
class PlanDeCuentas extends AccountingTool
{
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'solo_imputables' => ['nullable', 'boolean'],
        ]);

        $term = mb_strtolower(trim((string) $request->get('buscar', '')));

        $accounts = Account::with('parent')
            ->orderBy('code')
            ->when($request->get('solo_imputables'), fn ($q) => $q->postable())
            ->get()
            ->when($term !== '', fn ($c) => $c->filter(
                fn (Account $a): bool => str_contains(mb_strtolower($a->code.' '.$a->name), $term)
            ));

        return $this->respond([
            'cuentas' => $accounts->map(fn (Account $a): array => [
                ...$this->accountRef($a),
                'padre' => $a->parent?->code,
                'imputable' => $a->is_postable,
                'auxiliar' => $a->is_auxiliary,
                'activa' => $a->is_active,
                'seccion_pnl' => $a->effectivePnlSection()?->value,
                'seccion_flujo' => $a->effectiveCashFlowSection()?->value,
            ])->values(),
            'total' => $accounts->count(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'buscar' => $schema->string()->description('Filtra por código o nombre (opcional).'),
            'solo_imputables' => $schema->boolean()->description('Solo cuentas que reciben asientos (imputables y activas).'),
        ];
    }
}
