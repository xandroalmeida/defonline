<?php

declare(strict_types=1);

use App\Domain\Motor\FonteRecomendacoes;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RecomendacoesCongeladas;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Feature tests usam a TestCase do Laravel + RefreshDatabase (Postgres real
| via docker compose, conforme princípio #3/#6 e quality-standards).
| Unit tests rodam só com PHPUnit base — rápidos, sem DB.
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->beforeEach(function () {
    // STORY-093 CA-6: o provedor fake recusa chamada feita dentro de transação (o nível de agora é o do teste).
    FakePaymentProvider::$nivelForaDeTransacao = DB::transactionLevel();
})->in('Feature');

// Domain: testes do núcleo de regras de negócio (app/Domain). Boot do Laravel
// é necessário para `config(...)` e service container, mas NÃO usa banco: a matriz
// de recomendações vem de um arquivo congelado (PDR-016).
// Gate de cobertura ≥ 98% via phpunit-domain.xml (STORY-010 + STORY-028).
pest()->extend(TestCase::class)->beforeEach(function () {
    app()->bind(FonteRecomendacoes::class, RecomendacoesCongeladas::class);
})->in('Domain');

// STORY-076 — ajudantes compartilhados dos testes de pagamento.
require_once __DIR__.'/Support/pagamento.php';
require_once __DIR__.'/Support/drm_serie.php';

// Teste sob demanda contra o sandbox do Asaas (grupo `asaas-sandbox`, fora da suíte padrão).
pest()->extend(TestCase::class)->in('Sandbox');
