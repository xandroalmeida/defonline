<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo') — {{ config('app.name', 'DEFOnline') }}</title>
    <style>
        :root { color-scheme: light; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; background: #f3f4f6; color: #111827; }
        .legal { max-width: 720px; margin: 2rem auto; padding: 2rem; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); line-height: 1.55; }
        .legal h1 { margin: 0 0 .25rem; font-size: 1.6rem; }
        .legal h2 { margin-top: 1.75rem; font-size: 1.15rem; }
        .meta { color: #6b7280; font-size: .875rem; margin-bottom: 1.5rem; }
        .banner-placeholder { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: .85rem 1rem; border-radius: 8px; margin: 0 0 1.5rem; }
        .banner-placeholder p { margin: .35rem 0 0; font-size: .9rem; }
        a { color: #2563eb; }
        ul { padding-left: 1.25rem; }
    </style>
</head>
<body>
    <main class="legal" dusk="legal-conteudo">
        <h1>@yield('titulo')</h1>
        <p class="meta">
            Versão <strong dusk="legal-versao">@yield('versao')</strong>
            · atualizado em <span dusk="legal-atualizado-em">@yield('atualizado_em')</span>
        </p>

        @yield('conteudo')
    </main>
</body>
</html>
