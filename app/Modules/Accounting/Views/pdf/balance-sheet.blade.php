@extends('accounting::pdf.layout')

@section('title', 'Balance general')

@section('content')
    <h1>Balance general</h1>
    <p class="meta">Al {{ \Illuminate\Support\Carbon::parse($report['as_of'])->format('d/m/Y') }}</p>

    @foreach ($report['sections'] as $key => $section)
        <div class="section">{{ $section['label'] }}</div>
        <table>
            @foreach ($section['rows'] as $row)
                <tr>
                    <td style="padding-left: {{ 4 + $row['level'] * 14 }}px">{{ $row['account']->code }} {{ $row['account']->name }}</td>
                    <td class="num">{{ number_format((float) $row['amount'], 2) }}</td>
                </tr>
            @endforeach
            @if ($key === 'equity')
                <tr>
                    <td><em>Resultado del período</em></td>
                    <td class="num"><em>{{ number_format((float) $report['result'], 2) }}</em></td>
                </tr>
            @endif
            <tr class="total">
                <td>Total {{ $section['label'] }}{{ $key === 'equity' ? ' + Resultado' : '' }}</td>
                <td class="num">{{ number_format((float) ($key === 'equity' ? bcadd($section['total'], $report['result'], 2) : $section['total']), 2) }}</td>
            </tr>
        </table>
    @endforeach

    <div class="check {{ $report['check']['balanced'] ? 'ok' : 'bad' }}">
        Activo = Pasivo + Patrimonio + Resultado:
        {{ number_format((float) $report['check']['assets'], 2) }} vs {{ number_format((float) $report['check']['liabilities_equity'], 2) }}
        ({{ $report['check']['balanced'] ? 'OK' : 'NO BALANCEA' }})
    </div>
@endsection
