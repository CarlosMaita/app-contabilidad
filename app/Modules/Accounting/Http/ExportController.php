<?php

namespace App\Modules\Accounting\Http;

use App\Modules\Accounting\Exports\BalanceSheetExport;
use App\Modules\Accounting\Exports\GeneralLedgerExport;
use App\Modules\Accounting\Exports\JournalBookExport;
use App\Modules\Accounting\Exports\ProfitAndLossExport;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Accounting\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function balance(Request $request, ReportService $reports)
    {
        $validated = $request->validate([
            'format' => ['required', 'in:pdf,xlsx'],
            'as_of' => ['required', 'date'],
        ]);

        $report = $reports->balanceSheet($request->user()->id, $validated['as_of']);
        $filename = "balance-general-{$validated['as_of']}";

        if ($validated['format'] === 'xlsx') {
            return Excel::download(new BalanceSheetExport($report), "{$filename}.xlsx");
        }

        return Pdf::loadView('accounting::pdf.balance-sheet', ['report' => $report])
            ->download("{$filename}.pdf");
    }

    public function pnl(Request $request, ReportService $reports)
    {
        $validated = $request->validate([
            'format' => ['required', 'in:pdf,xlsx'],
            'from' => ['nullable', 'date'],
            'to' => ['required', 'date'],
        ]);

        $report = $reports->profitAndLoss($request->user()->id, $validated['from'] ?? null, $validated['to']);
        $filename = "estado-de-resultados-{$validated['to']}";

        if ($validated['format'] === 'xlsx') {
            return Excel::download(new ProfitAndLossExport($report), "{$filename}.xlsx");
        }

        return Pdf::loadView('accounting::pdf.profit-and-loss', ['report' => $report])
            ->download("{$filename}.pdf");
    }

    public function journal(Request $request)
    {
        $validated = $request->validate([
            'format' => ['required', 'in:pdf,xlsx'],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $entries = JournalEntry::with(['lines.account', 'execution.operationType'])
            ->filtered($validated['search'] ?? '', $validated['from'] ?? '', $validated['to'] ?? '')
            ->orderBy('date')
            ->orderBy('number')
            ->get();

        if ($validated['format'] === 'xlsx') {
            return Excel::download(new JournalBookExport($entries), 'libro-diario.xlsx');
        }

        return Pdf::loadView('accounting::pdf.journal-book', ['entries' => $entries])
            ->download('libro-diario.pdf');
    }

    public function ledger(Request $request, LedgerService $ledger)
    {
        $validated = $request->validate([
            'format' => ['required', 'in:pdf,xlsx'],
            'account_id' => ['required', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        // El global scope garantiza que la cuenta sea del usuario.
        $account = Account::findOrFail((int) $validated['account_id']);

        $result = $ledger->movements($account, $validated['from'] ?? null, $validated['to'] ?? null);

        if ($validated['format'] === 'xlsx') {
            return Excel::download(new GeneralLedgerExport($account, $result), "libro-mayor-{$account->code}.xlsx");
        }

        return Pdf::loadView('accounting::pdf.general-ledger', ['account' => $account, 'result' => $result])
            ->download("libro-mayor-{$account->code}.pdf");
    }
}
