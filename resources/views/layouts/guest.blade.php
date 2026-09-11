<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Contabilidad') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans">
        <div class="grid min-h-screen md:grid-cols-2">
            <div class="hidden flex-col gap-6 border-r-2 border-ink p-12 md:flex">
                <div class="text-[13px] font-extrabold uppercase tracking-[0.12em]">Contabilidad por eventos</div>
                <div class="max-w-[9em] text-[38px] font-extrabold leading-[1.08] tracking-tight">
                    Operaciones que se convierten en asientos.
                </div>
                <div class="max-w-[34em] text-sm text-neutral-800">
                    Definí tus operaciones, ejecutalas con un formulario y el mapeo contable
                    genera los asientos de doble partida. Diario, mayor, balance y P&amp;L al instante.
                </div>
                <div class="mt-auto grid gap-2 font-mono text-xs text-neutral-700">
                    <div>doble partida · asientos inmutables</div>
                    <div>verificación: Activo = Pasivo + Patrimonio</div>
                </div>
            </div>

            <div class="flex items-center p-6 sm:p-12">
                <div class="w-full max-w-[380px]">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
