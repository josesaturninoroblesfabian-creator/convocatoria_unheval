<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('title') · UNHEVAL</title>
    <style>
        body { font: 15px/1.6 Arial, sans-serif; color: #14334b; max-width: 780px; margin: 48px auto; padding: 0 22px; }
        h1 { font-size: 27px; margin: 8px 0 14px; }
        h2 { font-size: 19px; margin-top: 26px; }
        small { color: #536575; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 10px 12px; text-align: left; vertical-align: top; border-bottom: 1px solid #ddd; overflow-wrap: anywhere; }
        thead { background: #f3f6f8; }
        button { padding: 12px 24px; margin-top: 20px; background: #073b54; color: #fff; border: 0; cursor: pointer; }
        .hash { overflow-wrap: anywhere; font: 12px monospace; background: #f3f6f8; padding: 15px; }
        .signature { margin-top: 70px; }
        @media print {
            body { margin: 20px; padding: 0; }
            button { display: none; }
            tr { break-inside: avoid; }
            h2 { break-after: avoid; }
        }
    </style>
</head>
<body>
    <small>UNIVERSIDAD NACIONAL HERMILIO VALDIZÁN · VICERRECTORADO ACADÉMICO</small>
    <h1>@yield('title')</h1>
    @if ($application->recruitmentCall->is_demo)
        <p><strong>DEMOSTRACIÓN. Documento de prueba sin validez oficial.</strong></p>
    @endif
    @yield('content')
    <button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button>
</body>
</html>
