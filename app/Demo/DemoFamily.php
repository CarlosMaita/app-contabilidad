<?php

namespace App\Demo;

use App\Books\BookService;
use App\Books\BookTransferService;
use App\Books\InBook;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Operations\Actions\ExecuteOperation;
use App\Modules\Operations\Models\OperationType;
use App\Modules\Shared\Books\Book;
use Illuminate\Support\Carbon;

/**
 * Contabilidad compartida de ejemplo para la demo: «Familia (demo)» con
 * plantilla Hogar, aportes desde la contabilidad personal (transferencias
 * enlazadas) y gastos comunes pagados con el fondo.
 */
class DemoFamily
{
    public function __construct(
        private readonly BookService $books,
        private readonly BookTransferService $transfers,
        private readonly ExecuteOperation $execute,
    ) {}

    public function populate(User $user): Book
    {
        $family = $this->books->create($user, 'Familia (demo)', 'household');
        $personal = $user->personalBook();

        $code = fn (Book $book, string $code): int => Account::withoutGlobalScopes()
            ->where('book_id', $book->id)->where('code', $code)->value('id');

        foreach ([[1, 5, '400000'], [0, 2, '350000']] as [$monthsAgo, $day, $amount]) {
            InBook::run($personal, $user, fn () => $this->transfers->transfer($user, [
                'from' => $personal, 'from_cash' => $code($personal, '1.1.02'),
                'to' => $family, 'to_cash' => $code($family, '1.1.01'),
                'amount' => $amount, 'date' => $this->date($monthsAgo, $day), 'description' => 'Aporte al fondo familiar',
            ]));
        }

        $expenses = [
            [1, 8, '6.1.01', '38500', 'Electricidad'],
            [1, 10, '6.1.02', '24900', 'Internet fibra'],
            [1, 14, '6.2.01', '182300', 'Supermercado del mes'],
            [1, 21, '6.3.01', '95000', 'Pintura del living'],
            [0, 3, '6.1.01', '41200', 'Electricidad'],
            [0, 4, '6.1.02', '24900', 'Internet fibra'],
            [0, 5, '6.2.01', '96400', 'Supermercado'],
        ];

        InBook::run($family, $user, function () use ($family, $code, $expenses): void {
            $type = OperationType::where('code', 'pagar_gasto')->sole();

            foreach ($expenses as [$monthsAgo, $day, $account, $amount, $detail]) {
                $this->execute->execute($type, [
                    'gasto' => $code($family, $account), 'monto' => $amount, 'detalle' => $detail,
                ], $this->date($monthsAgo, $day));
            }
        });

        return $family;
    }

    /** Día del mes de hace N meses, sin pasarse del fin de mes ni de hoy. */
    private function date(int $monthsAgo, int $day): string
    {
        $month = Carbon::today()->startOfMonth()->subMonths($monthsAgo);

        return $month->copy()->day(min($day, $month->daysInMonth))->min(Carbon::today())->toDateString();
    }
}
