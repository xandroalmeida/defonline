<?php

declare(strict_types=1);

use App\Domain\Motor\FonteRecomendacoes;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

// Domain: testes do núcleo de regras de negócio (app/Domain). Boot do Laravel
// é necessário para `config(...)` e service container, mas NÃO usa banco: a matriz
// de recomendações vem de um arquivo congelado (PDR-016).
// Gate de cobertura ≥ 98% via phpunit-domain.xml (STORY-010 + STORY-028).
pest()->extend(TestCase::class)->beforeEach(function () {
    app()->bind(FonteRecomendacoes::class, RecomendacoesCongeladas::class);
})->in('Domain');
