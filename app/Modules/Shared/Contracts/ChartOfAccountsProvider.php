<?php

namespace App\Modules\Shared\Contracts;

use Illuminate\Support\Collection;

/**
 * Contrato que expone el plan de cuentas del usuario autenticado a otros
 * módulos sin acoplarlos a los modelos de Accounting.
 */
interface ChartOfAccountsProvider
{
    /**
     * Cuentas imputables y activas del usuario autenticado.
     *
     * @return Collection<int, object{id: int, code: string, name: string}>
     */
    public function postableAccounts(): Collection;
}
