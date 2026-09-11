<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Exceptions\MappingResolutionException;
use App\Modules\Shared\Money\Money;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

/**
 * Evalúa expresiones de monto del mapeo (`monto * 0.21`, `total / 1.21`)
 * sobre el payload de una ejecución. Solo permite las funciones de la
 * whitelist; nunca eval().
 */
class ExpressionEvaluator
{
    private const ALLOWED_FUNCTIONS = ['round', 'abs', 'min', 'max'];

    private ExpressionLanguage $language;

    public function __construct()
    {
        // Anónima para no heredar constant() ni enum(), registradas por defecto.
        $this->language = new class extends ExpressionLanguage
        {
            protected function registerFunctions(): void
            {
                foreach (ExpressionEvaluator::allowedFunctions() as $name) {
                    $this->addFunction(ExpressionFunction::fromPhp($name));
                }
            }
        };
    }

    /** @return array<int, string> */
    public static function allowedFunctions(): array
    {
        return self::ALLOWED_FUNCTIONS;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return string monto normalizado a 2 decimales
     */
    public function evaluate(string $expression, array $variables): string
    {
        try {
            $result = $this->language->evaluate($expression, $this->numericCast($variables));
        } catch (Throwable $e) {
            throw new MappingResolutionException(
                "Expresión inválida «{$expression}»: {$e->getMessage()}",
                previous: $e,
            );
        }

        if (! is_numeric($result)) {
            throw new MappingResolutionException("La expresión «{$expression}» no devuelve un número.");
        }

        return Money::normalize($result);
    }

    /**
     * Los decimales del payload viajan como strings; se castean para operar.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function numericCast(array $variables): array
    {
        return array_map(
            fn (mixed $v): mixed => is_string($v) && is_numeric($v) ? (float) $v : $v,
            $variables,
        );
    }
}
