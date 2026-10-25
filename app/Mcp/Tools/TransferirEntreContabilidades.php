<?php

namespace App\Mcp\Tools;

use App\Books\BookTransferService;
use App\Modules\Accounting\Models\Account;
use App\Modules\Shared\Books\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('transferir_entre_contabilidades')]
#[Description('Registra un movimiento de dinero entre dos contabilidades del usuario (ej. aporte de su Binance personal al Binance familiar, o un reintegro). Genera un asiento en cada una, enlazados; revertir uno revierte ambos. Las contrapartidas son automáticas salvo que se indiquen: en compartidas, la cuenta «Aportes de {usuario}».')]
class TransferirEntreContabilidades extends AccountingTool
{
    public function handle(Request $request, BookTransferService $transfers): Response|ResponseFactory
    {
        $v = $request->validate([
            'desde' => ['required'],
            'cuenta_salida' => ['required'],
            'hacia' => ['required'],
            'cuenta_entrada' => ['required'],
            'monto' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'descripcion' => ['required', 'string', 'max:300'],
            'contrapartida_salida' => ['nullable'],
            'contrapartida_entrada' => ['nullable'],
        ]);

        $from = $this->book($v['desde'], 'desde');
        $to = $this->book($v['hacia'], 'hacia');

        return $this->attempt(function () use ($transfers, $v, $from, $to) {
            $t = $transfers->transfer(auth()->user(), [
                'from' => $from,
                'from_cash' => $this->accountIn($from, $v['cuenta_salida'], 'cuenta_salida')->id,
                'from_counter' => filled($v['contrapartida_salida'] ?? null) ? $this->accountIn($from, $v['contrapartida_salida'], 'contrapartida_salida')->id : null,
                'to' => $to,
                'to_cash' => $this->accountIn($to, $v['cuenta_entrada'], 'cuenta_entrada')->id,
                'to_counter' => filled($v['contrapartida_entrada'] ?? null) ? $this->accountIn($to, $v['contrapartida_entrada'], 'contrapartida_entrada')->id : null,
                'amount' => (string) $v['monto'],
                'date' => $v['fecha'],
                'description' => $v['descripcion'],
            ]);

            // Cada asiento es de otra contabilidad: sus cuentas se cargan sin el scope de la actual.
            $crossBook = ['lines.account' => fn ($q) => $q->withoutGlobalScopes(), 'execution.operationType', 'reverses'];

            return $this->respond([
                'mensaje' => "Transferencia registrada: asiento #{$t->fromEntry->number} en «{$from->label()}» y #{$t->toEntry->number} en «{$to->label()}».",
                'salida' => $this->entryData($t->fromEntry->load($crossBook)),
                'entrada' => $this->entryData($t->toEntry->load($crossBook)),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'desde' => $schema->string()->description('Contabilidad de origen: id o nombre («personal», «Familia Maita»…).')->required(),
            'cuenta_salida' => $schema->string()->description('Código de la cuenta de efectivo de donde sale el dinero, en el origen (ej. «1.1.02»).')->required(),
            'hacia' => $schema->string()->description('Contabilidad de destino: id o nombre.')->required(),
            'cuenta_entrada' => $schema->string()->description('Código de la cuenta de efectivo donde entra, en el destino (ej. «1.1.01» Binance familiar).')->required(),
            'monto' => $schema->number()->description('Monto positivo, hasta 2 decimales.')->required(),
            'fecha' => $schema->string()->description('Fecha AAAA-MM-DD.')->required(),
            'descripcion' => $schema->string()->description('Ej. «Aporte de octubre».')->required(),
            'contrapartida_salida' => $schema->string()->description('Opcional: código de la contrapartida en el origen.'),
            'contrapartida_entrada' => $schema->string()->description('Opcional: código de la contrapartida en el destino.'),
        ];
    }

    private function book(mixed $reference, string $field): Book
    {
        $reference = mb_strtolower(trim((string) $reference));

        return auth()->user()->books()->get()->first(fn (Book $b): bool => (string) $b->id === $reference
                || mb_strtolower($b->name) === $reference
                || mb_strtolower($b->label()) === $reference
                || ($b->isPersonal() && $reference === 'personal'))
            ?? throw ValidationException::withMessages([$field => "No sos miembro de una contabilidad «{$reference}»."]);
    }

    private function accountIn(Book $book, mixed $code, string $field): Account
    {
        return Account::withoutGlobalScopes()->where('book_id', $book->id)->where('code', trim((string) $code))->first()
            ?? throw ValidationException::withMessages([$field => "La cuenta «{$code}» no existe en «{$book->label()}»."]);
    }
}
