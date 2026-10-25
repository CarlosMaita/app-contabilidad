<?php

namespace App\Mcp\Tools;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\ReportService;
use App\Modules\Operations\Enums\ExecutionStatus;
use App\Modules\Operations\Models\OperationExecution;
use App\Modules\Shared\Books\CurrentBook;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('resumen_cuenta')]
#[Description('Panorama de la cuenta contable: activo total, control del balance, ingresos/gastos/resultado del mes en curso, eventos sin contabilizar, cantidad de asientos y fecha de cierre del período. Buen primer paso.')]
#[IsReadOnly]
class ResumenCuenta extends AccountingTool
{
    public function handle(Request $request, ReportService $reports): ResponseFactory
    {
        $user = auth()->user();
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $balance = $reports->balanceSheet(CurrentBook::id(), $today);
        $month = $reports->profitAndLoss(CurrentBook::id(), $monthStart, $today);

        return $this->respond([
            'usuario' => $user->name,
            'fecha' => $today,
            'contabilidad' => CurrentBook::get()->label(),
            'periodo_cerrado_hasta' => CurrentBook::get()->accounting_closed_until?->toDateString(),
            'activo_total' => $balance['check']['assets'],
            'pasivo_patrimonio_resultado' => $balance['check']['liabilities_equity'],
            'balance_cuadra' => $balance['check']['balanced'],
            'mes_en_curso' => [
                'desde' => $monthStart,
                'hasta' => $today,
                'ingresos' => $month['total_income'],
                'gastos' => $month['total_expense'],
                'resultado' => $month['lines']['net'],
            ],
            'eventos_sin_contabilizar' => OperationExecution::whereIn('status', [
                ExecutionStatus::Unmapped, ExecutionStatus::Failed, ExecutionStatus::Pending,
            ])->count(),
            'asientos_registrados' => JournalEntry::count(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
