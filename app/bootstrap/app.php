<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnrichLogContext;
use App\Http\Middleware\MeasureRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust dos headers X-Forwarded-* injetados pelo Caddy (ADR-005 §3 —
        // Caddyfile.j2 envia X-Forwarded-Proto/Host/For). Sem isso, Laravel
        // ignora os headers, assume scheme=http (o que `artisan serve` vê
        // internamente) e gera URLs `http://...` em `asset()`/`url()` — causando
        // Mixed Content no Livewire (`<script src="http://defonline.../livewire.min.js">`
        // numa página servida via HTTPS). Bug rastreado em 2026-05-24 durante
        // smoke manual da STORY-018 em homologação.
        //
        // `at: '*'` é seguro neste cenário: o container `web` só aceita conexões
        // da rede Docker interna (sem `ports:` mapeado publicamente em homol/prod
        // — ADR-002), então o único upstream possível é o Caddy.
        $middleware->trustProxies(at: '*');

        // Ordem importa: request_id PRIMEIRO, depois enriquece log, depois mede.
        $middleware->prepend(AssignRequestId::class);
        $middleware->web(append: [
            EnrichLogContext::class,
            MeasureRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // STORY-013 CA-3 — link de confirmação inválido/expirado vai pra tela
        // amigável de erro com o motivo, em vez do 403 padrão do middleware `signed`.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->routeIs('email.confirmar')) {
                return redirect()
                    ->route('email.confirmar-erro')
                    ->with('email_confirmar_erro_motivo', 'expirado');
            }

            return null;
        });
    })->create();
