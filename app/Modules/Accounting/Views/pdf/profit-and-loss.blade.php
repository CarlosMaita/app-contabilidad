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

    @php($money = fn ($v) => number_format((float) $v, 2, ',', '.'))
    @php($cascade = $report['cascade'])

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Cuenta / Línea</th>
                <th class="num">Importe</th>
                <th class="num">Margen</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cascade as $item)
                @if ($item[0] === 'section')
                    @php($s = $report['sections'][$item[1]])
                    @if (count($s['rows']) > 0 || (float) $s['total'] != 0)
                        <tr>
                            <td></td>
                            <td style="text-transform: uppercase; font-size: 9px; letter-spacing: 0.08em; color: #605d5d;">{{ $item[2] === '−' ? '(−) ' : '' }}{{ $s['label'] }}</td>
                            <td class="num" style="color: #605d5d;">{{ $money($s['total']) }}</td>
                            <td></td>
                        </tr>
                        @foreach ($s['rows'] as $row)
                            <tr>
                                <td style="font-family: DejaVu Sans Mono, monospace;">{{ $row['account']->code }}</td>
                                <td style="padding-left: {{ 4 + $row['level'] * 12 }}px">{{ $row['account']->name }}</td>
                                <td class="num">{{ $money($row['amount']) }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                    @endif
                @else
                    <tr class="{{ $item[0] === 'line' ? 'total' : '' }}">
                        <td></td>
                        <td style="font-weight: bold;">{{ $item[2] }}</td>
                        <td class="num" style="font-weight: bold;">{{ $money($report['lines'][$item[1]]) }}</td>
                        <td class="num" style="font-size: 9px; color: #605d5d;">
                            @if ($item[0] === 'line' && ($item[3] ?? null) !== null && $report['margins'][$item[3]] !== null)
                                {{ str_replace('.', ',', $report['margins'][$item[3]]) }}%
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="check {{ (float) $report['lines']['net'] >= 0 ? 'ok' : 'bad' }}">
        Resultado Neto: {{ $money($report['lines']['net']) }}
        @if ($report['margins']['net'] !== null)
            · Margen neto: {{ str_replace('.', ',', $report['margins']['net']) }}%
        @endif
    </div>
@endsection
