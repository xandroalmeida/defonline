<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// STORY-015 CA-5 — monitor de taxa de erro da consulta RFB (NRF §3.1).
// `withoutOverlapping` evita corrida quando uma execução demora; `onOneServer`
// previne disparos duplicados quando houver mais de uma réplica do scheduler.
Schedule::command('rfb:monitorar-error-rate')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping(10);

// STORY-076 / ADR-010 — retoma webhooks de pagamento gravados e não aplicados.
Schedule::command('cobranca:reprocessar-eventos')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping(10);

// STORY-074 CA-6 — convite para assinar em D-2 e D-0 do trial (cada aviso sai uma vez por trial).
Schedule::command('trial:avisar-fim')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(30);

// STORY-077 CA-6 — cota mensal das assinaturas anuais, no dia de aniversário.
Schedule::command('assinaturas:renovar-cota')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(30);
