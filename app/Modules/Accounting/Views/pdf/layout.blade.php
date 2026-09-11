<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .meta { color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { text-align: left; text-transform: uppercase; font-size: 9px; color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 4px; }
        td { padding: 3px 4px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .total { font-weight: bold; border-top: 1px solid #9ca3af; }
        .section { font-size: 13px; font-weight: bold; margin: 10px 0 4px; }
        .check { padding: 6px; margin-top: 8px; font-weight: bold; }
        .ok { background: #ecfdf5; color: #047857; }
        .bad { background: #fef2f2; color: #b91c1c; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
