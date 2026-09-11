<?php

namespace App\Modules\Operations\Services;

use App\Models\User;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Operations\Models\OperationVariable;

/**
 * Borra todos los datos operativos de un usuario. Debe correr después de
 * la purga contable: los asientos referencian las ejecuciones.
 */
class PurgeOperationsData
{
    public static function forUser(User $user): void
    {
        OperationExecution::withoutGlobalScopes()->where('user_id', $user->id)->delete();

        $types = OperationType::withoutGlobalScopes()->where('user_id', $user->id);
        OperationVariable::whereIn('operation_type_id', (clone $types)->select('id'))->delete();
        (clone $types)->delete();
    }
}
