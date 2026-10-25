<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Exceptions\ClosedPeriodException;
use App\Modules\Accounting\Exceptions\MappingResolutionException;
use App\Modules\Accounting\Exceptions\UnbalancedEntryException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use RuntimeException;
use UnitEnum;

/**
 * Base de las tools del servidor contable. Todo corre como el dueño del
 * token (auth:sanctum fija Auth::user()), así el global scope de
 * BelongsToBook limita cada consulta a su contabilidad actual.
 */
abstract class AccountingTool extends Tool
{
    /**
     * Ejecuta la tool y convierte los errores de dominio en errores de tool
     * con su mensaje (las validaciones las formatea el paquete).
     */
    protected function attempt(Closure $callback): Response|ResponseFactory
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            throw $e;
        } catch (ModelNotFoundException) {
            return Response::error('No se encontró el registro pedido en esta cuenta.');
        } catch (UnbalancedEntryException|ClosedPeriodException|MappingResolutionException|RuntimeException $e) {
            return Response::error($e->getMessage());
        }
    }

    protected function respond(array $data): ResponseFactory
    {
        return Response::structured($this->export($data));
    }

    /** Busca una cuenta del usuario por código (p. ej. «1.1.02») o por id. */
    protected function findAccount(string|int|null $reference, string $field = 'cuenta'): Account
    {
        $reference = trim((string) $reference);

        $account = Account::where('code', $reference)->first()
            ?? (ctype_digit($reference) ? Account::find((int) $reference) : null);

        return $account ?? throw ValidationException::withMessages([
            $field => "La cuenta «{$reference}» no existe en el plan de cuentas.",
        ]);
    }

    /** Convierte modelos, enums, fechas y colecciones en datos JSON simples. */
    protected function export(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Account => $this->accountRef($value),
            $value instanceof UnitEnum => $value instanceof \BackedEnum ? $value->value : $value->name,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            $value instanceof Collection => $this->export($value->all()),
            is_array($value) => array_map(fn ($v) => $this->export($v), $value),
            is_object($value) => $this->export(get_object_vars($value)),
            default => $value,
        };
    }

    /** Asiento con sus líneas (requiere lines.account, execution.operationType y reverses cargados). */
    protected function entryData(JournalEntry $entry): array
    {
        return [
            'numero' => $entry->number,
            'fecha' => $entry->date->toDateString(),
            'descripcion' => $entry->description,
            'estado' => $entry->status->value,
            'origen' => $entry->execution?->operationType?->name ?? 'Manual',
            'revierte_asiento' => $entry->reverses?->number,
            'lineas' => $entry->lines->map(fn ($line): array => [
                'cuenta' => $line->account->code,
                'nombre' => $line->account->name,
                'debe' => (string) $line->debit,
                'haber' => (string) $line->credit,
                'memo' => $line->memo,
            ])->all(),
        ];
    }

    /** @return array{codigo: string, nombre: string, id: int, tipo: string} */
    protected function accountRef(Account $account): array
    {
        return [
            'codigo' => $account->code,
            'nombre' => $account->name,
            'id' => $account->id,
            'tipo' => $account->type->value,
        ];
    }
}
