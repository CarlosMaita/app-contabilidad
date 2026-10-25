<?php

use App\Books\BookService;
use App\Livewire\Books\ManageBooks;
use App\Models\User;
use App\Modules\Accounting\Enums\EntrySide;
use App\Modules\Accounting\Livewire\JournalBook;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\JournalEntryBuilder;
use App\Modules\Accounting\Services\ReportService;
use App\Modules\Operations\Actions\ExecuteOperation;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Shared\Books\Book;
use App\Modules\Shared\Books\CurrentBook;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function familyBook(User $owner, string $name = 'Familia Maita'): Book
{
    return app(BookService::class)->create($owner, $name, 'household');
}

function code(Book $book, string $code): Account
{
    return Account::withoutGlobalScopes()->where('book_id', $book->id)->where('code', $code)->firstOrFail();
}

test('cada usuario tiene su contabilidad personal y es la actual por defecto', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $personal = bookOf($user);

    expect($personal->isPersonal())->toBeTrue()
        ->and($personal->hasMember($user))->toBeTrue()
        ->and(CurrentBook::id())->toBe($personal->id);
});

test('la plantilla Hogar trae plan, operaciones y la cuenta de aportes del dueño', function () {
    $owner = User::factory()->create(['name' => 'Carlos']);
    $book = familyBook($owner);

    $aportes = Account::withoutGlobalScopes()->where('book_id', $book->id)->where('name', 'Aportes de Carlos')->sole();

    expect($book->template)->toBe('household')
        ->and(code($book, '1.1.01')->name)->toBe('Binance familiar')
        ->and(code($book, '6.1.01')->name)->toBe('Electricidad')
        ->and($aportes->is_auxiliary)->toBeTrue()
        ->and($aportes->parent->code)->toBe('3.1')
        ->and($aportes->parent->is_postable)->toBeFalse()
        ->and(OperationType::withoutGlobalScopes()->where('book_id', $book->id)->pluck('code')->sort()->values()->all())
        ->toBe(['aporte_miembro', 'pagar_gasto', 'rendimiento']);
});

test('una contabilidad Negocio compartida crea el aporte de cada socio bajo Capital', function () {
    $owner = User::factory()->create(['name' => 'Carlos']);
    $partner = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com']);
    $book = app(BookService::class)->create($owner, 'Fotografía', 'business');

    app(BookService::class)->invite($book, $owner, 'ana@example.com');

    $aux = Account::withoutGlobalScopes()->where('book_id', $book->id)->where('parent_id', code($book, '3.1')->id)->pluck('name')->all();
    expect($aux)->toBe(['Aportes de Carlos', 'Aportes de Ana'])
        ->and($book->fresh()->hasMember($partner))->toBeTrue();
});

test('los miembros comparten los datos y un tercero no los ve ni puede entrar', function () {
    $carlos = User::factory()->create();
    $esposa = User::factory()->create(['email' => 'esposa@example.com']);
    $tercero = User::factory()->create();
    $book = familyBook($carlos);
    app(BookService::class)->invite($book, $carlos, 'esposa@example.com');

    // Carlos registra un gasto en la contabilidad familiar.
    $this->actingAs($carlos)->post(route('books.switch', $book))->assertRedirect(route('dashboard'));
    app(ExecuteOperation::class)->execute(
        OperationType::where('code', 'pagar_gasto')->sole(),
        ['gasto' => code($book, '6.1.01')->id, 'monto' => '45.50', 'detalle' => 'Luz de octubre'],
        '2026-10-05',
    );
    expect(JournalEntry::count())->toBe(1);

    // La esposa lo ve en la misma contabilidad.
    $this->actingAs($esposa)->post(route('books.switch', $book));
    CurrentBook::flush();
    expect(JournalEntry::sole()->description)->toBe('Luz de octubre')
        ->and(JournalEntry::sole()->author->is($carlos))->toBeTrue();

    // Su contabilidad personal sigue vacía y aparte.
    $this->post(route('books.switch', bookOf($esposa)));
    CurrentBook::flush();
    expect(JournalEntry::count())->toBe(0);

    // Un tercero no puede cambiarse a la familiar.
    $this->actingAs($tercero)->post(route('books.switch', $book))->assertForbidden();
    expect(JournalEntry::count())->toBe(0);
});

test('la numeración de asientos y el cierre son propios de cada contabilidad', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $book = familyBook($user);
    $book->update(['accounting_closed_until' => '2026-09-30']);

    $line = fn (Book $b, string $date) => app(JournalEntryBuilder::class)->post(
        bookId: $b->id, date: $date, description: 'x',
        lines: [
            ['side' => EntrySide::Debit, 'account_id' => code($b, '6.2.01')->id, 'amount' => '10.00', 'memo' => null],
            ['side' => EntrySide::Credit, 'account_id' => code($b, '1.1.01')->id, 'amount' => '10.00', 'memo' => null],
        ],
    );

    expect(fn () => $line($book, '2026-09-15'))->toThrow(RuntimeException::class);

    $first = $line($book, '2026-10-01');
    expect($first->number)->toBe(1)
        ->and(app(ReportService::class)->balanceSheet(bookOf($user)->id, '2026-12-31')['check']['assets'])->toBe('0.00');
});

test('invitar a un correo sin cuenta lo suma al registrarse', function () {
    $owner = User::factory()->create();
    $book = familyBook($owner);

    expect(app(BookService::class)->invite($book, $owner, 'Nueva@Example.com'))->toBeNull();

    $nueva = User::factory()->create(['email' => 'nueva@example.com']);
    event(new Registered($nueva));

    expect($book->fresh()->hasMember($nueva))->toBeTrue()
        ->and($book->invitations()->count())->toBe(0)
        ->and(Account::withoutGlobalScopes()->where('book_id', $book->id)->where('name', "Aportes de {$nueva->name}")->exists())->toBeTrue();
});

test('solo el dueño administra; un miembro puede salir', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['email' => 'm@example.com']);
    $book = familyBook($owner);
    app(BookService::class)->invite($book, $owner, 'm@example.com');

    expect(fn () => app(BookService::class)->invite($book, $member, 'otro@example.com'))
        ->toThrow(HttpException::class);

    $this->actingAs($member);
    Livewire::test(ManageBooks::class)
        ->assertSee('Familia Maita')
        ->assertDontSee('Borrar contabilidad')
        ->call('leave', $book->id);

    expect($book->fresh()->hasMember($member))->toBeFalse();
});

test('desde la página se crea una contabilidad y queda como actual', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('books.index'))->assertOk()->assertSee('Mis contabilidades')->assertSee('Contabilidad personal');

    Livewire::test(ManageBooks::class)
        ->set('name', 'Fotografía')
        ->set('template', 'business')
        ->call('create')
        ->assertRedirect(route('dashboard'));

    $book = Book::where('name', 'Fotografía')->sole();
    expect($user->fresh()->current_book_id)->toBe($book->id)
        ->and(Account::withoutGlobalScopes()->where('book_id', $book->id)->count())->toBeGreaterThan(50);

    $this->get(route('dashboard'))->assertSee('Fotografía');
});

test('el dueño borra una compartida con sus datos; la personal no se puede borrar', function () {
    $owner = User::factory()->create();
    $book = familyBook($owner);
    $this->actingAs($owner);

    Livewire::test(ManageBooks::class)->call('destroy', $book->id);

    expect(Book::find($book->id))->toBeNull()
        ->and(Account::withoutGlobalScopes()->where('book_id', $book->id)->count())->toBe(0);

    expect(fn () => app(BookService::class)->delete(bookOf($owner), $owner))
        ->toThrow(ValidationException::class);
});

test('al borrar al dueño, la compartida pasa a otro miembro', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['email' => 'm@example.com']);
    $book = familyBook($owner);
    app(BookService::class)->invite($book, $owner, 'm@example.com');
    $soloMine = familyBook($owner, 'Solo mía');

    $owner->delete();

    expect($book->fresh()->owner_id)->toBe($member->id)
        ->and(Book::find($soloMine->id))->toBeNull();
});

test('el libro diario muestra quién registró en una compartida', function () {
    $owner = User::factory()->create(['name' => 'Carlos']);
    $book = familyBook($owner);
    $this->actingAs($owner)->post(route('books.switch', $book));
    CurrentBook::flush();

    app(JournalEntryBuilder::class)->post(bookId: $book->id, date: '2026-10-01', description: 'Súper', lines: [
        ['side' => EntrySide::Debit, 'account_id' => code($book, '6.2.01')->id, 'amount' => '30.00', 'memo' => null],
        ['side' => EntrySide::Credit, 'account_id' => code($book, '1.1.01')->id, 'amount' => '30.00', 'memo' => null],
    ]);

    Livewire::test(JournalBook::class)->assertSee('Súper')->assertSee('por Carlos');
});
