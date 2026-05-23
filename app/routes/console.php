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
