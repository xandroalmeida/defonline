<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 720px; margin: 4rem auto; padding: 2rem; color: #1f2937;">
    <h1 style="margin: 0 0 0.5rem; font-size: 2.25rem;">hello {{ $appName }}</h1>

    <p style="color: #6b7280; margin: 0 0 2rem;">
        Foundation técnica em pé — STORY-007 Phase 1 (local).
    </p>

    <dl style="display: grid; grid-template-columns: max-content 1fr; gap: 0.5rem 1.5rem; padding: 1rem 1.25rem; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">
        <dt style="font-weight: 600;">Versão</dt>
        <dd dusk="app-version" style="margin: 0; font-family: ui-monospace, SFMono-Regular, monospace;">{{ $version }}</dd>

        <dt style="font-weight: 600;">Ambiente</dt>
        <dd style="margin: 0;">{{ $env }}</dd>

        <dt style="font-weight: 600;">Healthcheck</dt>
        <dd dusk="health-status" style="margin: 0;">
            <span style="display: inline-block; padding: 2px 10px; border-radius: 999px; background: {{ $status === 'OK' ? '#dcfce7' : '#fee2e2' }}; color: {{ $status === 'OK' ? '#166534' : '#991b1b' }}; font-weight: 600;">
                {{ $status }}
            </span>
        </dd>

        <dt style="font-weight: 600;">request_id</dt>
        <dd style="margin: 0; font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.85em; color: #4b5563;">{{ $requestId }}</dd>
    </dl>

    <div style="margin-top: 2rem;">
        <button
            type="button"
            wire:click="dispararEmail"
            dusk="disparar-email"
            style="padding: 0.625rem 1.25rem; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;"
        >
            Disparar e-mail de teste (web → fila → worker → Mailpit)
        </button>
        @if ($mensagemEnvio !== '')
            <p dusk="mensagem-envio" style="margin-top: 1rem; color: #16a34a;">{{ $mensagemEnvio }}</p>
        @endif
    </div>

    <p style="margin-top: 2.5rem; font-size: 0.85em; color: #9ca3af;">
        Endpoints técnicos:
        <a href="/health" style="color: #2563eb;">/health</a> ·
        <a href="/ready" style="color: #2563eb;">/ready</a> ·
        Mailpit: <a href="http://localhost:8025" target="_blank" rel="noopener" style="color: #2563eb;">localhost:8025</a>
    </p>
</div>
