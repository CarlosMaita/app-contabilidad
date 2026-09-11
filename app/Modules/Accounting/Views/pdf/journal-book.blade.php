@extends('accounting::pdf.layout')

@section('title', 'Libro diario')

@section('content')
    <h1>Libro diario</h1>
    <p class="meta">Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>N°</th>
                <th>Fecha</th>
                <th>Descripción</th>
                <th>Cuenta</th>
                <th class="num">Debe</th>
                <th class="num">Haber</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                @foreach ($entry->lines as $line)
                    <tr>
                        @if ($loop->first)
                            <td rowspan="{{ $entry->lines->count() }}">{{ $entry->number }}</td>
                            <td rowspan="{{ $entry->lines->count() }}">{{ $entry->date->format('d/m/Y') }}</td>
                            <td rowspan="{{ $entry->lines->count() }}">
                                {{ $entry->description }}
                                @if ($entry->status->value === 'reversed') (revertido) @endif
                            </td>
                        @endif
                        <td>{{ $line->account->code }} {{ $line->account->name }}</td>
                        <td class="num">{{ $line->debit != 0 ? number_format((float) $line->debit, 2) : '' }}</td>
                        <td class="num">{{ $line->credit != 0 ? number_format((float) $line->credit, 2) : '' }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
@endsection
