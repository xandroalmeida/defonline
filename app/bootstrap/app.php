<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnrichLogContext;
use App\Http\Middleware\MeasureRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })->create();
