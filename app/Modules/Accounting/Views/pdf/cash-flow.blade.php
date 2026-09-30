@extends('accounting::pdf.layout')

@section('title', 'Estado de flujo de efectivo')

@section('content')
    <h1>Estado de flujo de efectivo</h1>
    <p class="meta">
        Método indirecto ·
        @if ($report['from'])
            Del {{ \Illuminate\Support\Carbon::parse($report['from'])->format('d/m/Y') }}
        @endif
        al {{ \Illuminate\Support\Carbon::parse($report['to'])->format('d/m/Y') }}
    </p>

    @php($money = fn ($v) => number_format((float) $v, 2, ',', '.'))
    @php($s = $report['sections'])

    <table>
        <tbody>
            <tr><td colspan="3" class="section">Actividades operativas</td></tr>
            <tr>
                <td></td>
                <td>Resultado neto del período</td>
                <td class="num">{{ $money($report['net_income']) }}</td>
            </tr>
            @foreach (['non_cash' => 'Ajustes sin movimiento de efectivo', 'operating' => 'Variaciones en capital de trabajo'] as $key => $label)
                @if (count($s[$key]['rows']) > 0)
                    <tr><td></td><td style="color: #605d5d; font-size: 9px;">{{ $label }}</td><td></td></tr>
                    @foreach ($s[$key]['rows'] as $row)
                        <tr>
                            <td style="font-family: DejaVu Sans Mono, monospace;">{{ $row['account']->code }}</td>
                            <td style="padding-left: 16px;">{{ $row['account']->name }}</td>
                            <td class="num">{{ $money($row['amount']) }}</td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
            <tr class="total">
                <td></td>
                <td>Flujo neto de actividades operativas</td>
                <td class="num">{{ $money($report['totals']['operating']) }}</td>
            </tr>

            @foreach (['investing' => ['Actividades de inversión', 'Flujo neto de actividades de inversión'], 'financing' => ['Actividades de financiamiento', 'Flujo neto de actividades de financiamiento']] as $key => [$title, $totalLabel])
                <tr><td colspan="3" class="section">{{ $title }}</td></tr>
                @forelse ($s[$key]['rows'] as $row)
                    <tr>
                        <td style="font-family: DejaVu Sans Mono, monospace;">{{ $row['account']->code }}</td>
                        <td style="padding-left: 16px;">{{ $row['account']->name }}</td>
                        <td class="num">{{ $money($row['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td></td><td>Sin movimientos.</td><td></td></tr>
                @endforelse
                <tr class="total">
                    <td></td>
                    <td>{{ $totalLabel }}</td>
                    <td class="num">{{ $money($report['totals'][$key]) }}</td>
                </tr>
            @endforeach

            <tr class="total">
                <td></td>
                <td>Variación neta del efectivo</td>
                <td class="num">{{ $money($report['net_change']) }}</td>
            </tr>
            <tr>
                <td></td>
                <td>Efectivo al inicio del período</td>
                <td class="num">{{ $money($report['cash_opening']) }}</td>
            </tr>
            <tr class="total">
                <td></td>
                <td>Efectivo al final del período</td>
                <td class="num">{{ $money($report['cash_closing']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="check {{ $report['check']['balanced'] ? 'ok' : 'bad' }}">
        Variación calculada = variación real de las cuentas de efectivo:
        {{ $money($report['net_change']) }} vs {{ $money($report['check']['actual_change']) }}
        ({{ $report['check']['balanced'] ? 'OK' : 'NO CONCILIA' }})
    </div>
@endsection
