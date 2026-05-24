<?php

declare(strict_types=1);

use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\Rfb\RfbCnpjStatus;
use App\Domain\SituacaoCadastral;
use App\Services\Rfb\CnpjaRfbCnpjClient;
use App\Services\Rfb\RfbCnpjFalhouException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * STORY-018 CA-2 / CA-5 / CA-8 — cliente cnpja.
 *
 * Cobre os 5 cenários canônicos (sucesso, cnpj_inexistente, erro_5xx do 5xx,
 * erro_5xx do 429, timeout, erro_rede) + rate-limit local fail-fast + header
 * de autenticação só com api_key configurada.
 *
 * Não toca rede de verdade (Http::fake); o cenário contratual real fica fora
 * desta suíte (anotado como `@group external` na STORY).
 */
function carregarFixtureCnpja(string $arquivo): array
{
    $caminho = base_path("tests/Fixtures/Rfb/cnpja/{$arquivo}");

    return (array) json_decode((string) file_get_contents($caminho), true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(function () {
    // RPM alto + chave de rate-limit limpa = cada cenário começa com slot livre.
    config()->set('services.rfb.providers.cnpja.rate_limit_per_minute', 100);
    config()->set('services.rfb.providers.cnpja.api_key', null);
    // Default da config aponta para o Open API gratuito (3 RPM, sem token) —
    // confirmado no smoke contratual de 2026-05-23 contra o endpoint real.
    config()->set('services.rfb.providers.cnpja.base_url', 'https://open.cnpja.com');
    config()->set('services.rfb.timeout', 5);
    RateLimiter::clear('rfb:provider:cnpja');
    Http::preventStrayRequests();
});

it('mapeia resposta 200 do cnpja (Open API atual) para RfbCnpjResult (CA-2)', function () {
    // Fixture sucesso.json é a resposta REAL do Open API capturada em
    // 2026-05-23 (CNPJ 00.000.000/0001-91 — Banco do Brasil), reduzida aos
    // campos que usamos. Garante que o mapeamento bate com o schema atual.
    Http::fake([
        'open.cnpja.com/office/*' => Http::response(carregarFixtureCnpja('sucesso.json'), 200),
    ]);

    $cliente = new CnpjaRfbCnpjClient;
    $resultado = $cliente->consultarCnpj('00000000000191');

    expect($resultado)->toBeInstanceOf(RfbCnpjResult::class);
    expect($resultado->razaoSocial)->toBe('BANCO DO BRASIL SA');
    expect($resultado->nomeFantasia)->toBe('Direcao Geral');
    expect($resultado->cnae)->toBe('6422100');
    expect($resultado->municipio)->toBe('Brasília'); // lido de address.city
    expect($resultado->uf)->toBe('DF');
    expect($resultado->situacaoCadastral)->toBe(SituacaoCadastral::Ativa);
    expect($resultado->dataFundacao?->format('Y-m-d'))->toBe('1966-08-01');
    expect($resultado->fonteProvedor)->toBe('cnpja');
});

it('aceita schema legado: address.municipality como string (sem address.city)', function () {
    // Defesa para o plano pago caso ainda sirva o nome em `municipality`.
    Http::fake([
        'open.cnpja.com/office/*' => Http::response(carregarFixtureCnpja('sucesso-schema-legado.json'), 200),
    ]);

    $resultado = (new CnpjaRfbCnpjClient)->consultarCnpj('12345678000195');

    expect($resultado->nomeFantasia)->toBeNull();
    expect($resultado->municipio)->toBe('Sao Paulo');
    expect($resultado->uf)->toBe('SP'); // veio "sp" minúsculo
    expect($resultado->situacaoCadastral)->toBe(SituacaoCadastral::Suspensa);
    expect($resultado->cnae)->toBe('4721102');
});

it('falha com Erro5xx quando address.city ausente e municipality vem como código IBGE numérico', function () {
    Http::fake([
        'open.cnpja.com/office/*' => Http::response([
            'company' => ['name' => 'X SA'],
            'status' => ['text' => 'Ativa'],
            'address' => ['municipality' => 5300108, 'state' => 'DF'], // sem city
            'mainActivity' => ['id' => 6422100, 'text' => 'x'],
        ], 200),
    ]);

    try {
        (new CnpjaRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::Erro5xx);
        expect($e->getMessage())->toContain('município');
    }
});

it('mapeia 404 para cnpj_inexistente (CA-2)', function () {
    Http::fake([
        'open.cnpja.com/office/*' => Http::response(carregarFixtureCnpja('404.json'), 404),
    ]);

    expect(fn () => (new CnpjaRfbCnpjClient)->consultarCnpj('00000000000191'))
        ->toThrow(RfbCnpjFalhouException::class);

    try {
        (new CnpjaRfbCnpjClient)->consultarCnpj('00000000000191');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::CnpjInexistente);
        expect($e->provedor)->toBe('cnpja');
    }
});

it('mapeia 429 do provedor para erro_5xx (CA-2)', function () {
    Http::fake([
        'open.cnpja.com/office/*' => Http::response(carregarFixtureCnpja('429.json'), 429),
    ]);

    try {
        (new CnpjaRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado RfbCnpjFalhouException');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::Erro5xx);
        expect($e->provedor)->toBe('cnpja');
    }
});

it('mapeia 5xx para erro_5xx (CA-2)', function () {
    Http::fake([
        'open.cnpja.com/office/*' => Http::response(carregarFixtureCnpja('500.json'), 500),
    ]);

    try {
        (new CnpjaRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado RfbCnpjFalhouException');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::Erro5xx);
    }
});

it('mapeia ConnectionException com "timed out" para Timeout (CA-2)', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out');
    });

    try {
        (new CnpjaRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::Timeout);
    }
});

it('mapeia ConnectionException sem timeout para ErroRede (CA-2)', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 6: Could not resolve host');
    });

    try {
        (new CnpjaRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::ErroRede);
    }
});

it('envia header Authorization quando api_key configurada', function () {
    config()->set('services.rfb.providers.cnpja.api_key', 'meu-token-secreto');
    Http::fake([
        'open.cnpja.com/office/*' => Http::response(carregarFixtureCnpja('sucesso.json'), 200),
    ]);

    (new CnpjaRfbCnpjClient)->consultarCnpj('00000000000191');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'meu-token-secreto');
    });
});

it('não envia header Authorization quando api_key vazia (plano gratuito)', function () {
    Http::fake([
        'open.cnpja.com/office/*' => Http::response(carregarFixtureCnpja('sucesso.json'), 200),
    ]);

    (new CnpjaRfbCnpjClient)->consultarCnpj('00000000000191');

    Http::assertSent(function ($request) {
        return ! $request->hasHeader('Authorization');
    });
});

it('respeita rate-limit por provedor com fail-fast (CA-5)', function () {
    config()->set('services.rfb.providers.cnpja.rate_limit_per_minute', 2);
    RateLimiter::clear('rfb:provider:cnpja');

    Http::fake([
        'open.cnpja.com/office/*' => Http::response(carregarFixtureCnpja('sucesso.json'), 200),
    ]);

    $cliente = new CnpjaRfbCnpjClient;
    $cliente->consultarCnpj('00000000000191');
    $cliente->consultarCnpj('00000000000191');

    try {
        $cliente->consultarCnpj('00000000000191');
        $this->fail('deveria ter estourado rate-limit');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::Erro5xx);
        expect($e->provedor)->toBe('cnpja');
        expect($e->getMessage())->toContain('Rate-limit');
    }
});

it('falha com Erro5xx quando o corpo 200 está sem campo obrigatório', function () {
    Http::fake([
        'open.cnpja.com/office/*' => Http::response([
            'company' => ['name' => ''], // razão social vazia
        ], 200),
    ]);

    try {
        (new CnpjaRfbCnpjClient)->consultarCnpj('00000000000191');
        $this->fail('deveria ter lançado');
    } catch (RfbCnpjFalhouException $e) {
        expect($e->status)->toBe(RfbCnpjStatus::Erro5xx);
    }
});
