<?php

declare(strict_types=1);

use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\Rfb\RfbCnpjStatus;
use App\Services\Rfb\MockRfbCnpjClient;
use App\Services\Rfb\RfbCnpjClient;
use App\Services\Rfb\RfbCnpjFalhouException;
use App\Services\Rfb\RfbConsultarCnpj;
use Database\Factories\EmpresaAnalisadaFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Feature da STORY-015 cobrindo CA-3 (fonte rfb), CA-4 (métrica + audit) e
 * CA-6 (cache por SHA-256).
 */
beforeEach(function () {
    Cache::flush();
});

function chamarRfb(string $cnpj): RfbCnpjResult|RfbCnpjFalhouException
{
    try {
        return app(RfbConsultarCnpj::class)->executar($cnpj);
    } catch (RfbCnpjFalhouException $e) {
        return $e;
    }
}

it('registra métrica e audit em sucesso com provider/status no meta (CA-4)', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

    $resultado = chamarRfb($cnpj);

    expect($resultado)->toBeInstanceOf(RfbCnpjResult::class);

    $metric = DB::table('business_metrics')->where('tipo', 'rfb_consulta')->first();
    expect($metric)->not->toBeNull();
    expect((bool) $metric->sucesso)->toBeTrue();
    $meta = json_decode($metric->meta, true);
    expect($meta['provider'])->toBe('mock');
    expect($meta['status'])->toBe(RfbCnpjStatus::Sucesso->value);
    expect($metric->duracao_ms)->toBeGreaterThanOrEqual(0);

    $audit = DB::table('audit_logs')->where('action', 'empresa.rfb_consultada')->first();
    expect($audit)->not->toBeNull();
    $context = json_decode($audit->context, true);
    expect($context['cnpj_sha256'])->toBe(hash('sha256', $cnpj));
    expect($context['status'])->toBe('sucesso');
    expect($context['provider'])->toBe('mock');
});

it('registra métrica e audit em falha com status correto (CA-4)', function (string $raiz, string $statusEsperado) {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz($raiz);

    $erro = chamarRfb($cnpj);

    expect($erro)->toBeInstanceOf(RfbCnpjFalhouException::class);
    expect($erro->status->value)->toBe($statusEsperado);

    $metric = DB::table('business_metrics')->where('tipo', 'rfb_consulta')->latest('id')->first();
    expect((bool) $metric->sucesso)->toBeFalse();
    $meta = json_decode($metric->meta, true);
    expect($meta['status'])->toBe($statusEsperado);
})->with([
    ['00112233', 'cnpj_inexistente'],
    ['99887766', 'timeout'],
    ['88776655', 'erro_5xx'],
    ['77665544', 'erro_rede'],
]);

it('nunca armazena o CNPJ cru no audit_logs (PII)', function () {
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('99887766'); // dispara timeout

    chamarRfb($cnpj);

    $audit = DB::table('audit_logs')->where('action', 'empresa.rfb_consultada')->first();
    $linha = $audit->context.'|'.($audit->before ?? '').'|'.($audit->after ?? '');
    expect(str_contains($linha, $cnpj))->toBeFalse();
});

it('ignora cache quando provider=mock (sempre retorna fresh) — CA-6', function () {
    // Default config é mock; duas chamadas devem inserir 2 métricas.
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

    chamarRfb($cnpj);
    chamarRfb($cnpj);

    expect(DB::table('business_metrics')->where('tipo', 'rfb_consulta')->count())->toBe(2);
});

it('usa cache na 2ª chamada quando provider≠mock (CA-6)', function () {
    config(['services.rfb.provider' => 'cnpja', 'services.rfb.cache_ttl' => 300]);
    // Após a STORY-018 o bind passa a resolver CnpjaRfbCnpjClient — que faria HTTP
    // real. Aqui o foco é o cache do orquestrador, não o cliente: troca o bind por
    // Mock e desliga o flag mock-no-cache via config explícita.
    app()->bind(RfbCnpjClient::class, fn () => new MockRfbCnpjClient);
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

    chamarRfb($cnpj);
    chamarRfb($cnpj);

    expect(DB::table('business_metrics')->where('tipo', 'rfb_consulta')->count())->toBe(1);
    expect(Cache::has('rfb:cnpj:'.hash('sha256', $cnpj)))->toBeTrue();
});

it('classifica throwable não-tipada do client como erro_rede', function () {
    app()->bind(RfbCnpjClient::class, fn () => new class implements RfbCnpjClient
    {
        public function consultarCnpj(string $cnpj): RfbCnpjResult
        {
            throw new RuntimeException('boom inesperado');
        }
    });

    $cnpj = EmpresaAnalisadaFactory::gerarCnpjValido();
    $erro = chamarRfb($cnpj);

    expect($erro)->toBeInstanceOf(RfbCnpjFalhouException::class);
    expect($erro->status)->toBe(RfbCnpjStatus::ErroRede);

    $metric = DB::table('business_metrics')->where('tipo', 'rfb_consulta')->first();
    $meta = json_decode($metric->meta, true);
    expect($meta['status'])->toBe('erro_rede');
});

it('rejeita DV inválido como cnpj_inexistente sem chamar o client', function () {
    $chamou = false;
    app()->bind(RfbCnpjClient::class, function () use (&$chamou) {
        return new class($chamou) implements RfbCnpjClient
        {
            public function __construct(public bool &$flag) {}

            public function consultarCnpj(string $cnpj): RfbCnpjResult
            {
                $this->flag = true;
                throw new RuntimeException('não deveria chegar aqui');
            }
        };
    });

    $erro = chamarRfb('11111111111111');

    expect($erro)->toBeInstanceOf(RfbCnpjFalhouException::class);
    expect($erro->status)->toBe(RfbCnpjStatus::CnpjInexistente);
    expect($chamou)->toBeFalse();
});

it('grava enriquecido_at no DTO consultado_at coerente com a hora do servidor', function () {
    Carbon::setTestNow('2026-05-23 10:00:00');
    $cnpj = EmpresaAnalisadaFactory::cnpjComRaiz('12345678');

    $resultado = app(RfbConsultarCnpj::class)->executar($cnpj);

    expect($resultado->consultadoAt->format('Y-m-d H:i:s'))->toBe('2026-05-23 10:00:00');

    Carbon::setTestNow();
});
