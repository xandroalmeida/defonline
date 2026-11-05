<?php

declare(strict_types=1);

use App\Domain\TermoTipo;
use App\Support\TermosVigentes;
use Illuminate\Support\Facades\View;

/**
 * STORY-012 CA-3/CA-4 — helper de termo vigente.
 *
 * Precisa do app booted para resolver Blade views (`View::make(...)->render()`).
 */
it('expõe versão v2 e rota pública dos termos obrigatórios', function () {
    $termoAdesao = TermosVigentes::para(TermoTipo::TermoAdesao);
    $lgpd = TermosVigentes::para(TermoTipo::Lgpd);

    expect($termoAdesao->versao)->toBe('v2');
    expect($termoAdesao->rota)->toBe('/termos/termo-adesao');

    expect($lgpd->versao)->toBe('v2');
    expect($lgpd->rota)->toBe('/termos/politica-privacidade');
});

it('calcula conteudo_hash como SHA-256 do HTML renderizado da view (CA-3, CA-4)', function () {
    $esperado = hash('sha256', View::make('legal.termo-adesao-v2')->render());

    expect(TermosVigentes::para(TermoTipo::TermoAdesao)->conteudoHash)->toBe($esperado);
    expect(TermosVigentes::para(TermoTipo::TermoAdesao)->conteudoHash)->toMatch('/^[a-f0-9]{64}$/');
});

it('preserva as views da v1 como registro do texto aceito nos cadastros antigos', function () {
    expect(View::exists('legal.termo-adesao-v1-placeholder'))->toBeTrue();
    expect(View::exists('legal.politica-privacidade-v1-placeholder'))->toBeTrue();
});

it('marketing tem hash determinístico baseado em tipo:versao (não há view própria)', function () {
    $marketing = TermosVigentes::para(TermoTipo::Marketing);

    expect($marketing->conteudoHash)->toBe(hash('sha256', 'marketing:v1-placeholder'));
});
