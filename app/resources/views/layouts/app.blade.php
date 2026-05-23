<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'DEFOnline') }}</title>
    @livewireStyles
    <style>
        :root { color-scheme: light; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; background: #f3f4f6; color: #111827; }
        .container { max-width: 480px; margin: 3rem auto; padding: 2rem; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .container--wide { max-width: 640px; }
        h1 { margin: 0 0 1.5rem; font-size: 1.5rem; }
        label { display: block; font-weight: 600; margin-top: 1rem; }
        input, select { width: 100%; padding: .625rem .75rem; font-size: 1rem; border: 1px solid #d1d5db; border-radius: 6px; margin-top: .25rem; box-sizing: border-box; background: white; }
        input:focus, select:focus { outline: 2px solid #2563eb; outline-offset: -1px; }
        input[type=checkbox], input[type=radio] { width: auto; margin: 0; padding: 0; }
        .erro { color: #b91c1c; margin-top: .25rem; font-size: .875rem; }
        button.primary { width: 100%; padding: .75rem; margin-top: 1.5rem; background: #2563eb; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        button.primary:hover { background: #1d4ed8; }
        a { color: #2563eb; }
        .sucesso { background: #d1fae5; color: #065f46; padding: .75rem; border-radius: 6px; margin-bottom: 1rem; }
        .info { color: #4b5563; font-size: .95rem; margin: 0 0 1.25rem; }
        nav.topo { display: flex; align-items: center; justify-content: space-between; max-width: 720px; margin: 1rem auto; padding: 0 1rem; }
        fieldset.aceites { border: 1px solid #e5e7eb; border-radius: 8px; padding: .75rem 1rem 1rem; margin-top: 1.25rem; }
        fieldset.aceites .aceites__legenda { padding: 0 .35rem; font-size: .85rem; color: #6b7280; }
        label.aceite { display: flex; gap: .55rem; align-items: flex-start; font-weight: 400; margin-top: .65rem; line-height: 1.35; }
        label.aceite input[type=checkbox] { width: auto; margin-top: .2rem; flex-shrink: 0; }
        label.aceite small { color: #6b7280; }
        fieldset.grupo { border: 1px solid #e5e7eb; border-radius: 8px; padding: .5rem 1rem 1rem; margin-top: 1.25rem; }
        fieldset.grupo legend { padding: 0 .35rem; font-size: .85rem; color: #6b7280; font-weight: 600; }
        label.radio { display: flex; gap: .55rem; align-items: center; font-weight: 400; margin-top: .5rem; cursor: pointer; }
        label small { color: #6b7280; font-weight: 400; }
        .badge { display: inline-block; padding: .25rem .65rem; background: #eef2ff; color: #3730a3; border-radius: 999px; font-size: .85rem; font-weight: 600; margin-bottom: 1rem; }
        dl.empresa-show { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1rem; margin: 1rem 0 1.5rem; }
        dl.empresa-show dt { font-weight: 600; color: #6b7280; font-size: .9rem; }
        dl.empresa-show dd { margin: 0; }
        a.botao { display: inline-block; padding: .5rem 1rem; border: 1px solid #d1d5db; border-radius: 6px; text-decoration: none; color: #6b7280; pointer-events: none; opacity: .6; }
        button.secondary { padding: .5rem 1rem; background: white; color: #2563eb; border: 1px solid #2563eb; border-radius: 6px; font-weight: 600; cursor: pointer; }
        button.secondary:hover:not(:disabled) { background: #eff6ff; }
        button.secondary:disabled { opacity: .55; cursor: not-allowed; }
        .rfb-consultar { margin-top: .5rem; display: flex; flex-direction: column; gap: .5rem; }
        .aviso { margin: .25rem 0 0; padding: .65rem .85rem; border-radius: 6px; font-size: .9rem; line-height: 1.35; }
        .aviso--alerta { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .aviso--sucesso { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    </style>
</head>
<body>
    {{ $slot ?? '' }}
    {{ $children ?? '' }}
    @yield('conteudo')
    @livewireScripts
</body>
</html>
