<?php

namespace App\Mcp\Tools;

use App\Modules\Shared\Books\Book;
use App\Modules\Shared\Books\CurrentBook;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('contabilidades')]
#[Description('Lista las contabilidades del usuario (la personal y las compartidas: familia, negocios con socios…) con su rol, miembros y cuál es la actual. Todas las demás tools trabajan sobre la contabilidad actual; para cambiarla usá «usar_contabilidad».')]
#[IsReadOnly]
class Contabilidades extends AccountingTool
{
    public function handle(Request $request): ResponseFactory
    {
        $current = CurrentBook::id();

        return $this->respond([
            'actual' => $current,
            'contabilidades' => auth()->user()->books()->with('members')
                ->orderByRaw("case when kind = 'personal' then 0 else 1 end")->orderBy('name')->get()
                ->map(fn (Book $book): array => [
                    'id' => $book->id,
                    'nombre' => $book->label(),
                    'tipo' => $book->isPersonal() ? 'personal' : 'compartida',
                    'plantilla' => $book->template,
                    'rol' => $book->pivot->role === Book::OWNER ? 'dueño' : 'miembro',
                    'miembros' => $book->members->pluck('name')->all(),
                    'actual' => $book->id === $current,
                ]),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
