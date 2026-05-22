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
