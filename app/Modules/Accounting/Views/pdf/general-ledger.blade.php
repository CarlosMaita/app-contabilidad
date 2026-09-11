@extends('accounting::pdf.layout')

@section('title', 'Libro mayor')

@section('content')
    <h1>Libro mayor — {{ $account->code }} {{ $account->name }}</h1>
    <p class="meta">{{ $account->type->label() }} (saldo {{ $account->type->isDebitNature() ? 'deudor' : 'acreedor' }}) — Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Asiento</th>
                <th>Descripción</th>
                <th class="num">Debe</th>
                <th class="num">Haber</th>
                <th class="num">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="5"><em>Saldo inicial</em></td>
                <td class="num">{{ number_format((float) $result['opening'], 2) }}</td>
            </tr>
            @foreach ($result['movements'] as $m)
                <tr>
                    <td>{{ $m->date->format('d/m/Y') }}</td>
                    <td>#{{ $m->number }}</td>
                    <td>{{ $m->description }}@if ($m->memo) — {{ $m->memo }}@endif</td>
                    <td class="num">{{ $m->debit != 0 ? number_format((float) $m->debit, 2) : '' }}</td>
                    <td class="num">{{ $m->credit != 0 ? number_format((float) $m->credit, 2) : '' }}</td>
                    <td class="num">{{ number_format((float) $m->balance, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="5">Saldo final</td>
                <td class="num">{{ number_format((float) $result['closing'], 2) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
