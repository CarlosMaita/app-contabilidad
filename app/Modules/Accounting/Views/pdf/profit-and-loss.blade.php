@extends('accounting::pdf.layout')

@section('title', 'Estado de resultados')

@section('content')
    <h1>Estado de resultados</h1>
    <p class="meta">
        @if ($report['from'])
            Del {{ \Illuminate\Support\Carbon::parse($report['from'])->format('d/m/Y') }}
        @endif
        al {{ \Illuminate\Support\Carbon::parse($report['to'])->format('d/m/Y') }}
    </p>

    @foreach (['income' => 'Ingresos', 'expense' => 'Gastos'] as $key => $label)
        <div class="section">{{ $label }}</div>
        <table>
            @forelse ($report[$key]['rows'] as $row)
                <tr>
                    <td style="padding-left: {{ 4 + $row['level'] * 14 }}px">{{ $row['account']->code }} {{ $row['account']->name }}</td>
                    <td class="num">{{ number_format((float) $row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">Sin movimientos.</td></tr>
            @endforelse
            <tr class="total">
                <td>Total {{ $label }}</td>
                <td class="num">{{ number_format((float) $report[$key]['total'], 2) }}</td>
            </tr>
        </table>
    @endforeach

    <div class="check {{ (float) $report['result'] >= 0 ? 'ok' : 'bad' }}">
        Resultado neto del período: {{ number_format((float) $report['result'], 2) }}
    </div>
@endsection
