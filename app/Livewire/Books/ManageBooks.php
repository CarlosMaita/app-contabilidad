<?php

namespace App\Livewire\Books;

use App\Books\BookService;
use App\Models\User;
use App\Modules\Shared\Books\Book;
use App\Modules\Shared\Books\CurrentBook;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mis contabilidades')]
class ManageBooks extends Component
{
    public string $name = '';

    public string $template = 'business';

    /** Correo a invitar, por contabilidad. @var array<int, string> */
    public array $invite = [];

    /** Nombre en edición, por contabilidad. @var array<int, string> */
    public array $rename = [];

    public function create(BookService $books): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'template' => ['required', Rule::in(array_keys(BookService::TEMPLATES))],
        ], [], ['name' => 'nombre', 'template' => 'plantilla']);

        $book = $books->create($this->user(), trim($this->name), $this->template);
        CurrentBook::switchTo($this->user(), $book);

        session()->flash('status', "Contabilidad «{$book->name}» creada. Ya estás trabajando en ella.");
        $this->redirectRoute('dashboard', navigate: true);
    }

    public function sendInvite(int $bookId, BookService $books): void
    {
        abort_if($this->user()->is_demo, 403, 'Las cuentas demo no pueden invitar.');

        $this->validate(["invite.{$bookId}" => ['required', 'email', 'max:255']], [], ["invite.{$bookId}" => 'correo']);

        $email = $this->invite[$bookId];
        $added = $books->invite($this->book($bookId), $this->user(), $email);

        $this->invite[$bookId] = '';
        session()->flash('status', $added !== null
            ? "{$added->name} ya es miembro de la contabilidad."
            : "Invitación guardada: cuando {$email} se registre en la app, va a ver esta contabilidad.");
    }

    public function cancelInvite(int $bookId, int $invitationId): void
    {
        $book = $this->book($bookId);
        abort_unless($book->isOwnedBy($this->user()), 403);

        $book->invitations()->whereKey($invitationId)->delete();
    }

    public function removeMember(int $bookId, int $userId, BookService $books): void
    {
        $books->removeMember($this->book($bookId), $this->user(), User::findOrFail($userId));
        session()->flash('status', 'Miembro quitado.');
    }

    public function leave(int $bookId, BookService $books): void
    {
        $book = $this->book($bookId);
        $books->leave($book, $this->user());

        session()->flash('status', "Saliste de «{$book->name}».");
        $this->redirectRoute('books.index', navigate: true);
    }

    public function saveName(int $bookId, BookService $books): void
    {
        $this->validate(["rename.{$bookId}" => ['required', 'string', 'max:120']], [], ["rename.{$bookId}" => 'nombre']);

        $books->rename($this->book($bookId), $this->user(), trim($this->rename[$bookId]));
        unset($this->rename[$bookId]);
    }

    public function destroy(int $bookId, BookService $books): void
    {
        $book = $this->book($bookId);
        $books->delete($book, $this->user());

        session()->flash('status', "Contabilidad «{$book->name}» borrada.");
        $this->redirectRoute('books.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.books.manage-books', [
            'books' => $this->user()->books()
                ->with(['members', 'invitations', 'owner'])
                ->orderByRaw("case when kind = 'personal' then 0 else 1 end")
                ->orderBy('name')
                ->get(),
            'currentId' => CurrentBook::id(),
            'templates' => BookService::TEMPLATES,
        ]);
    }

    private function user(): User
    {
        return auth()->user();
    }

    /** Solo contabilidades de las que el usuario es miembro. */
    private function book(int $id): Book
    {
        return $this->user()->books()->whereKey($id)->firstOrFail();
    }
}
