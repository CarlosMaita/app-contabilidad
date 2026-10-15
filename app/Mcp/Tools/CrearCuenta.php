<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\CashFlowSection;
use App\Modules\Accounting\Enums\PnlSection;
use App\Modules\Accounting\Services\AccountManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('crear_cuenta')]
#[Description('Agrega una cuenta al plan. Con «cuenta_padre» hereda tipo y secciones del padre, y el padre deja de ser imputable. Con «auxiliar» = true crea una cuenta auxiliar (p. ej. «CxC de Luis» bajo «1.1.03 Clientes por cobrar»): recibe imputaciones pero se consolida en la principal.')]
class CrearCuenta extends AccountingTool
{
    public function handle(Request $request, AccountManager $accounts): Response|ResponseFactory
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string'],
            'nombre' => ['required', 'string'],
            'tipo' => ['nullable', 'string'],
            'cuenta_padre' => ['nullable'],
            'auxiliar' => ['nullable', 'boolean'],
            'seccion_pnl' => ['nullable', 'string'],
            'seccion_flujo' => ['nullable', 'string'],
        ]);

        $parent = filled($validated['cuenta_padre'] ?? null)
            ? $this->findAccount($validated['cuenta_padre'], 'cuenta_padre')
            : null;

        if ($parent === null && blank($validated['tipo'] ?? null)) {
            throw ValidationException::withMessages(['tipo' => 'Indicá el tipo de cuenta o una cuenta padre.']);
        }

        $account = $accounts->save([
            'code' => $validated['codigo'],
            'name' => $validated['nombre'],
            'type' => $parent?->type->value ?? $validated['tipo'],
            'pnl_section' => $validated['seccion_pnl'] ?? null,
            'cash_flow_section' => $validated['seccion_flujo'] ?? null,
            'parent_id' => $parent?->id,
            'is_postable' => true,
            'is_auxiliary' => (bool) ($validated['auxiliar'] ?? false),
            'is_active' => true,
        ]);

        return $this->respond([
            'mensaje' => "Cuenta {$account->code} «{$account->name}» creada.",
            'cuenta' => [
                ...$this->accountRef($account),
                'padre' => $parent?->code,
                'auxiliar' => $account->is_auxiliary,
                'imputable' => $account->is_postable,
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'codigo' => $schema->string()->description('Código jerárquico, p. ej. «1.1.03.04».')->required(),
            'nombre' => $schema->string()->description('Nombre de la cuenta.')->required(),
            'tipo' => $schema->string()->enum(array_column(AccountType::cases(), 'value'))->description('Obligatorio si no hay cuenta padre.'),
            'cuenta_padre' => $schema->string()->description('Código o id de la cuenta padre (opcional).'),
            'auxiliar' => $schema->boolean()->description('Crear como auxiliar de la cuenta padre (requiere cuenta_padre).'),
            'seccion_pnl' => $schema->string()->enum(array_column(PnlSection::cases(), 'value'))->description('Solo ingresos/gastos sin padre; por defecto según el tipo.'),
            'seccion_flujo' => $schema->string()->enum(array_column(CashFlowSection::cases(), 'value'))->description('Solo cuentas de balance sin padre; por defecto según el tipo.'),
        ];
    }
}
