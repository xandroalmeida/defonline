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

// STORY-079 CA-1 — lembrete de renovação a 7 dias do fim do ciclo (um por ciclo).
Schedule::command('assinaturas:avisar-renovacao')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(30);

// STORY-079 CA-3 a CA-5 — régua: retentativas a cada 48 h, encerramento depois da última.
Schedule::command('cobranca:regua')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(30);

// STORY-079 CA-6 / ADR-010 §3 — conciliação horária (pendências) e diária (14 dias).
Schedule::command('cobranca:conciliar --modo=horaria')
    ->hourlyAt(20)
    ->onOneServer()
    ->withoutOverlapping(30);

Schedule::command('cobranca:conciliar --modo=diaria')
    ->dailyAt('06:30')
    ->onOneServer()
    ->withoutOverlapping(120);

// STORY-081 — termina o cancelamento da assinatura antiga das trocas de cartão (o provedor pode ter falhado na hora).
Schedule::command('assinaturas:cancelar-antigas')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(30);

// STORY-080 CA-4 e CA-5 — plano anual pago com Pix: aviso 7 dias antes do fim e encerramento sem renovação.
Schedule::command('assinaturas:pix-anual')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(30);

// STORY-083 CA-6 e CA-7 — ciclos gratuitos do desconto, fim do desconto sem cartão e valor de volta ao cheio no provedor.
Schedule::command('assinaturas:ciclos-gratuitos')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(30);

// STORY-083 CA-8 — aviso 7 dias antes do fim do desconto.
Schedule::command('cupons:avisar-fim-do-desconto')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(30);
