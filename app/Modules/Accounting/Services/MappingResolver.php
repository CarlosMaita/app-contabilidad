<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Exceptions\MappingResolutionException;
use App\Modules\Accounting\Models\AccountingMapping;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Shared\Money\Money;

class MappingResolver
{
    public function __construct(
        private readonly ExpressionEvaluator $expressions,
    ) {}

    /**
     * Resuelve las líneas del mapeo contra el payload de la ejecución.
     * Las líneas cuyo monto evalúa a 0 se descartan.
     *
     * @return array{description: string, lines: array<int, array{side: EntrySide, account_id: int, amount: string, memo: ?string}>}
     */
    public function resolve(AccountingMapping $mapping, OperationExecution $execution): array
    {
        $payload = $execution->payload ?? [];
        $lines = [];

        foreach ($mapping->lines as $mappingLine) {
            $accountId = $mappingLine->account_id;

            if ($accountId === null && $mappingLine->account_variable !== null) {
                $accountId = $payload[$mappingLine->account_variable] ?? null;

                if ($accountId === null) {
                    throw new MappingResolutionException(
                        "La variable «{$mappingLine->account_variable}» no trae una cuenta en el payload.",
                    );
                }
            }

            if ($accountId === null) {
                throw new MappingResolutionException('Hay una línea del mapeo sin cuenta asignada.');
            }

            $amount = $this->expressions->evaluate($mappingLine->amount_expression, $payload);

            if (Money::isNegative($amount)) {
                throw new MappingResolutionException(
                    "La expresión «{$mappingLine->amount_expression}» dio un monto negativo ({$amount}). Usá el lado opuesto del asiento.",
                );
            }

            if (Money::isZero($amount)) {
                continue;
            }

            $lines[] = [
                'side' => $mappingLine->side,
                'account_id' => (int) $accountId,
                'amount' => $amount,
                'memo' => $this->renderTemplate($mappingLine->memo_template, $payload),
            ];
        }

        $description = $this->renderTemplate($mapping->description_template, $payload)
            ?? $execution->description
            ?? $execution->operationType->name;

        return ['description' => $description, 'lines' => $lines];
    }

    /**
     * Reemplaza placeholders {variable} por valores del payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function renderTemplate(?string $template, array $payload): ?string
    {
        if ($template === null || trim($template) === '') {
            return null;
        }

        return preg_replace_callback(
            '/\{(\w+)\}/',
            fn (array $m): string => array_key_exists($m[1], $payload) && $payload[$m[1]] !== null
                ? (string) $payload[$m[1]]
                : $m[0],
            $template,
        );
    }
}
