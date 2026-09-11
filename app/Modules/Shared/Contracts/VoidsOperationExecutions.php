<?php

namespace App\Modules\Shared\Contracts;

/**
 * Permite al módulo operativo pedir la anulación de una ejecución
 * (contra-asiento incluido) sin conocer el módulo contable.
 */
interface VoidsOperationExecutions
{
    /**
     * @throws \RuntimeException si la ejecución no se puede anular
     */
    public function voidExecution(int $executionId): void;
}
