<?php

declare(strict_types=1);

use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\Rfb\RfbCnpjStatus;
use App\Domain\SituacaoCadastral;
use App\Services\Rfb\ReceitawsRfbCnpjClient;
use App\Services\Rfb\RfbCnpjFalhouException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * STORY-018 CA-3 / CA-5 / CA-8 — cliente receitaws.
 *
 * Cobre os 5 cenários canônicos + a particularidade do provedor: erros chegam
 * com HTTP 200 e `status: ERROR` no corpo, e o mapeamento depende do
 * `message` (CNPJ inválido vs Quota excedida).
 */
function carregarFixtureReceitaws(string $arquivo): array
{
    $caminho = base_path("tests/Fixtures/Rfb/receitaws/{$arquivo}");

    return (array) json_decode((string) file_get_contents($caminho), true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(function () {
    config()->set('services.rfb.providers.receitaws.rate_limit_per_minute', 100);
    config()->set('services.rfb.providers.receitaws.api_key', null);
    config()->set('services.rfb.providers.receitaws.base_url', 'https://receitaws.com.br/v1');
    config()->set('services.rfb.timeout', 5);
    RateLimiter::clear('rfb:provider:receitaws');
    Http::preventStrayRequests();
});

it('mapeia resposta status=OK para RfbCnpjResult (CA-3)', function () {
    Http::fake([
        'receitaws.com.br/v1/cnpj/*' => Http::response(carregarFixtureReceitaws('sucesso.json'), 200),
    ]);

    $resultado = (new ReceitawsRfbCnpjClient)->consultarCnpj('00000000000191');

    expect($resultado)->toBeInstanceOf(RfbCnpjResult::class);
    expect($resultado->razaoSocial)->toBe('BANCO DO BRASIL SA');
    expect($resultado->nomeFantasia)->toBe('BB');
    expect($resultado->cnae)->toBe('6422100');
    expect($resultado->municipio)->toBe('Brasilia');
    expect($resultado->uf)->toBe('DF');
    expect($resultado->situacaoCadastral)->toBe(SituacaoCadastral::Ativa);
    expect($resultado->dataFundacao?->format('Y-m-d'))->toBe('1966-08-01');
    expect($resultado->fonteProvedor)->toBe('receitaws');
});

it('normaliza fantasia vazia para null, UF para maiúsculo e mapeia Inapta', function () {
    Http::fake([
        'receitaws.com.br/v1/cnpj/*' => Http::response(carregarFixtureReceitaws('sucesso-fantasia-vazia.json'), 200),
    ]);

    $resultado = (new ReceitawsRfbCnpjClient)->consultarCnpj('12345678000195');

    expect($resultado->nomeFantasia)->toBeNull();
    expect($resultado->uf)->toBe('SP');
    expect($resultado->situacaoCadastral)->toBe(SituacaoCadastral::Inapta);
    expect($resultado->cnae)->toBe('4721102');
});

it('distingue status=ERROR com "CNPJ inválido" como cnpj_inexistente (CA-3)', function () {
    Http::fake([
        'receitaws.com.br/v1/cnpj/*' => Http::response(carregarFixtureReceitaws('erro-cnpj-invalido.json'), 200),
    ]);

    try {
        (new ReceitawsRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado RfbCnpjFalhouException');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::CnpjInexistente);
        expect($e->provedor)->toBe('receitaws');
    }
});

it('distingue status=ERROR com "Quota excedida" como erro_5xx (CA-3)', function () {
    Http::fake([
        'receitaws.com.br/v1/cnpj/*' => Http::response(carregarFixtureReceitaws('erro-quota.json'), 200),
    ]);

    try {
        (new ReceitawsRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::Erro5xx);
    }
});

it('mapeia HTTP 404 como cnpj_inexistente (CA-3)', function () {
    Http::fake([
        'receitaws.com.br/v1/cnpj/*' => Http::response('Not Found', 404),
    ]);

    try {
        (new ReceitawsRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::CnpjInexistente);
    }
});

it('mapeia HTTP 429 (rate-limit do provedor) como erro_5xx (CA-3)', function () {
    Http::fake([
        'receitaws.com.br/v1/cnpj/*' => Http::response('Too Many Requests', 429),
    ]);

    try {
        (new ReceitawsRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::Erro5xx);
    }
});

it('mapeia HTTP 5xx como erro_5xx (CA-3)', function () {
    Http::fake([
        'receitaws.com.br/v1/cnpj/*' => Http::response('Server error', 502),
    ]);

    try {
        (new ReceitawsRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::Erro5xx);
    }
});

it('mapeia ConnectionException com "timeout" para Timeout', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out');
    });

    try {
        (new ReceitawsRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::Timeout);
    }
});

it('mapeia ConnectionException sem "timeout" para ErroRede', function () {
    Http::fake(function () {
        throw new ConnectionException('DNS resolution failure');
    });

    try {
        (new ReceitawsRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::ErroRede);
    }
});

it('envia Authorization Bearer apenas quando api_key configurada', function () {
    config()->set('services.rfb.providers.receitaws.api_key', 'tk-receitaws-123');
    Http::fake([
        'receitaws.com.br/v1/cnpj/*' => Http::response(carregarFixtureReceitaws('sucesso.json'), 200),
    ]);

    (new ReceitawsRfbCnpjClient)->consultarCnpj('00000000000191');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer tk-receitaws-123');
    });
});

it('não envia Authorization quando api_key vazia', function () {
    Http::fake([
        'receitaws.com.br/v1/cnpj/*' => Http::response(carregarFixtureReceitaws('sucesso.json'), 200),
    ]);

    (new ReceitawsRfbCnpjClient)->consultarCnpj('00000000000191');

    Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
});

it('respeita rate-limit por provedor com fail-fast (CA-5)', function () {
    config()->set('services.rfb.providers.receitaws.rate_limit_per_minute', 1);
    RateLimiter::clear('rfb:provider:receitaws');

    Http::fake([
        'receitaws.com.br/v1/cnpj/*' => Http::response(carregarFixtureReceitaws('sucesso.json'), 200),
    ]);

    $cliente = new ReceitawsRfbCnpjClient;
    $cliente->consultarCnpj('00000000000191');

    try {
        $cliente->consultarCnpj('00000000000191');
        $this->fail('deveria ter estourado rate-limit');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::Erro5xx);
        expect($e->getMessage())->toContain('Rate-limit do provedor receitaws');
    }
});

it('falha com Erro5xx quando o corpo 200 não traz status reconhecível', function () {
    Http::fake([
        'receitaws.com.br/v1/cnpj/*' => Http::response(['mensagem' => 'sem status'], 200),
    ]);

    try {
        (new ReceitawsRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::Erro5xx);
    }
});
