<?php

namespace App\Mcp\Tools;

use App\Modules\Shared\Books\Book;
use App\Modules\Shared\Books\CurrentBook;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('usar_contabilidad')]
#[Description('Cambia la contabilidad actual (por id o nombre, ver «contabilidades»). A partir de ahí todas las tools consultan y registran en esa contabilidad. El cambio también se refleja en la web.')]
class UsarContabilidad extends AccountingTool
{
    public function handle(Request $request): ResponseFactory
    {
        $request->validate(['contabilidad' => ['required']]);

        $book = $this->findBook($request->get('contabilidad'));
        CurrentBook::switchTo(auth()->user(), $book);

        return $this->respond([
            'mensaje' => "Ahora trabajás en «{$book->label()}».",
            'id' => $book->id,
            'nombre' => $book->label(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'contabilidad' => $schema->string()->description('Id o nombre de la contabilidad (ej. «Familia Maita» o «personal»).')->required(),
        ];
    }

    private function findBook(mixed $reference): Book
    {
        $reference = trim((string) $reference);
        $books = auth()->user()->books()->get();

        return $books->first(fn (Book $b): bool => (string) $b->id === $reference
                || mb_strtolower($b->name) === mb_strtolower($reference)
                || mb_strtolower($b->label()) === mb_strtolower($reference)
                || ($b->isPersonal() && mb_strtolower($reference) === 'personal'))
            ?? throw ValidationException::withMessages(['contabilidad' => "No sos miembro de una contabilidad «{$reference}». Consultá «contabilidades»."]);
    }
}
