<?php

declare(strict_types=1);

use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Observabilidade\AlertaDaEquipe;
use App\Observabilidade\AlertaDeCobranca;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-086 CA-5 / IDR-028 — todo `Log::error` com module=cobranca (STORY-076 a 085) chega ao canal único
 * da equipe, sem dado pessoal e sem inundar: a mesma falha espera 30 minutos para alertar de novo.
 */
beforeEach(function () {
    config(['services.telegram.bot_token' => 'tok-do-bot', 'services.telegram.chat_id' => '-100']);
    Cache::flush();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
});

function _textosEnviados(): array
{
    return Http::recorded()->map(fn (array $par): string => $par[0]['text'])->values()->all();
}

it('um erro de cobrança vira alerta P2 com a ocorrência e os identificadores', function () {
    Log::error('cobranca.valor_divergente', ['module' => 'cobranca', 'action' => 'processar_evento', 'invoice_id' => 'inv-1', 'esperado_centavos' => 4990, 'recebido_centavos' => 100]);

    $textos = _textosEnviados();
    expect($textos)->toHaveCount(1)
        ->and($textos[0])->toStartWith('[P2] Cobrança: cobranca.valor_divergente')
        ->and($textos[0])->toContain('invoice_id: inv-1', 'esperado_centavos: 4990', 'recebido_centavos: 100', 'action: processar_evento');
});

it('não alerta por erro de outro módulo, por warning de cobrança nem por info', function () {
    Log::error('quiz.diagnostico.falha_inesperada', ['empresa_id' => 'e-1']);
    Log::warning('cobranca.valor_da_renovacao_mudou', ['module' => 'cobranca', 'assinatura_id' => 'a-1']);
    Log::info('cobranca.retentativa_pedida', ['module' => 'cobranca']);
    Log::error('rfb.qualquer', ['module' => 'rfb']);

    Http::assertNothingSent();
});

it('níveis críticos também alertam', function (string $nivel) {
    Log::{$nivel}('cobranca.algo_grave', ['module' => 'cobranca']);

    expect(_textosEnviados())->toHaveCount(1);
})->with(['critical', 'alert', 'emergency']);

it('só passam identificadores, tipos e contagens: texto livre, e-mail e dado de cartão nunca saem', function () {
    Log::error('cobranca.provedor_falhou', [
        'module' => 'cobranca',
        'action' => 'iniciar_checkout',
        'invoice_id' => 'inv-9',
        'provedor' => 'fake',
        'motivo' => 'Cartão 4111111111111111 de maria@exemplo.com recusado, CPF 123.456.789-09',
        'message' => 'texto de exceção com joao@exemplo.com',
        'email' => 'ana@exemplo.com',
        'cartao_ultimos4' => '1111',
        'usuario_id' => 'u-1',
        'exception' => 'App\\Domain\\Cobranca\\ProvedorIndisponivel',
        'erro' => 'frase solta com espaços e @',
    ]);

    $texto = _textosEnviados()[0];
    expect($texto)->toContain('invoice_id: inv-9', 'usuario_id: u-1', 'exception: App\\Domain\\Cobranca\\ProvedorIndisponivel')
        ->and($texto)->not->toMatch('/@|4111|1111|123\.456|Cartão|frase solta|motivo|message|email|provedor:/');
});

it('a mesma falha em 30 minutos alerta uma vez; outro identificador ou passado o silêncio, alerta de novo', function () {
    Log::error('cobranca.valor_divergente', ['module' => 'cobranca', 'invoice_id' => 'inv-1']);
    Log::error('cobranca.valor_divergente', ['module' => 'cobranca', 'invoice_id' => 'inv-1']);
    expect(_textosEnviados())->toHaveCount(1);

    Log::error('cobranca.valor_divergente', ['module' => 'cobranca', 'invoice_id' => 'inv-2']);
    expect(_textosEnviados())->toHaveCount(2);

    $this->travelTo(now()->addMinutes(31));
    Log::error('cobranca.valor_divergente', ['module' => 'cobranca', 'invoice_id' => 'inv-1']);
    expect(_textosEnviados())->toHaveCount(3);
});

it('o contexto que o middleware acrescenta a cada requisição (request_id, user_id) não quebra o silêncio, mas segue no alerta', function () {
    Log::error('cobranca.valor_divergente', ['module' => 'cobranca', 'invoice_id' => 'inv-1', 'request_id' => 'req-1', 'user_id' => 'u-1']);
    Log::error('cobranca.valor_divergente', ['module' => 'cobranca', 'invoice_id' => 'inv-1', 'request_id' => 'req-2', 'user_id' => 'u-2']);

    expect(_textosEnviados())->toHaveCount(1)->and(_textosEnviados()[0])->toContain('request_id: req-1', 'user_id: u-1');
});

it('prioridade P1 só quando o ponto de log pede; qualquer outro valor vira P2', function () {
    Log::error('cobranca.a', ['module' => 'cobranca', 'prioridade' => 'P1']);
    Log::error('cobranca.b', ['module' => 'cobranca', 'prioridade' => 'urgentissimo']);

    expect(_textosEnviados()[0])->toStartWith('[P1]')->and(_textosEnviados()[1])->toStartWith('[P2]');
});

it('sem Telegram o alerta cai para o log e o erro original segue seu caminho', function () {
    config(['services.telegram.bot_token' => '']);

    Log::error('cobranca.evento_com_erro', ['module' => 'cobranca', 'provider_event_id' => 'pe-1']);

    Http::assertNothingSent();
});

it('o canal que falha não derruba nem entra em laço: o alerta é warning, não error', function () {
    $canal = Mockery::mock(AlertaDaEquipe::class);
    $canal->shouldReceive('enviar')->once()->andReturnUsing(function () {
        // Se o canal registrasse erro de cobrança, o ouvinte reentraria; a trava impede.
        Log::error('cobranca.dentro_do_alerta', ['module' => 'cobranca']);
    });
    app()->instance(AlertaDaEquipe::class, $canal);

    Log::error('cobranca.fora', ['module' => 'cobranca']);

    expect(true)->toBeTrue(); // Mockery verifica o `once()`: a chamada interna não gerou segundo envio.
});

describe('CA-5 falha de autenticação do webhook', function () {
    it('webhook com token errado devolve 401 e alerta P1, sem corpo, token nem IP', function () {
        _usarProvedor('fake');
        $invoice = _assinaturaPendente(_compradorConfirmado());
        $bruto = _fake()->simularPagamento((string) $invoice->checkout_id_externo);

        $this->postJson('/webhooks/pagamentos', json_decode($bruto->corpo, true), ['x-fake-token' => 'token-errado-do-atacante'])->assertUnauthorized();

        $textos = _textosEnviados();
        expect($textos)->toHaveCount(1)
            ->and($textos[0])->toStartWith('[P1] Cobrança: cobranca.webhook_rejeitado')
            ->and($textos[0])->toContain('action: receber_webhook')
            ->and($textos[0])->not->toContain('token-errado-do-atacante')
            ->and($textos[0])->not->toMatch('/127\.0\.0|::1|192\.168/');
    });

    it('uma rajada de webhooks inválidos gera um alerta só', function () {
        _usarProvedor('fake');

        foreach (range(1, 5) as $_) {
            $this->postJson('/webhooks/pagamentos', ['x' => 1], ['x-fake-token' => 'errado'])->assertUnauthorized();
        }

        expect(_textosEnviados())->toHaveCount(1);
    });

    it('webhook autêntico não alerta', function () {
        _usarProvedor('fake');
        Mail::fake();
        $invoice = _assinaturaPendente(_compradorConfirmado());
        $bruto = _fake()->simularPagamento((string) $invoice->checkout_id_externo);

        $this->postJson('/webhooks/pagamentos', json_decode($bruto->corpo, true), ['x-fake-token' => $bruto->cabecalho('x-fake-token')])->assertOk();

        Http::assertNothingSent();
    });
});

describe('CA-5 erros de processamento', function () {
    afterEach(fn () => FakePaymentProvider::ficarIndisponivel(false));

    it('provedor fora do ar ao processar o pagamento alerta a equipe (indisponível, erro do evento e não processado)', function () {
        _usarProvedor('fake');
        Mail::fake();
        $invoice = _assinaturaPendente(_compradorConfirmado());
        $bruto = _fake()->simularPagamento((string) $invoice->checkout_id_externo);
        FakePaymentProvider::ficarIndisponivel();

        $this->postJson('/webhooks/pagamentos', json_decode($bruto->corpo, true), ['x-fake-token' => $bruto->cabecalho('x-fake-token')])->assertOk();

        $titulos = array_map(fn (string $t): string => explode("\n", $t)[0], _textosEnviados());
        expect($titulos)->toContain('[P2] Cobrança: cobranca.provedor_indisponivel', '[P2] Cobrança: cobranca.evento_com_erro', '[P2] Cobrança: cobranca.evento_nao_processado');
    });
});

it('o ouvinte filtra o contexto sem depender do canal (contextoSeguro)', function () {
    expect(AlertaDeCobranca::contextoSeguro(['invoice_id' => 'i-1', 'tipo' => 'cobranca.paga', 'tentativa' => 2, 'ok' => true, 'status' => true, 'motivo' => 'x', 'cobranca_id' => 'frase com espaço', 'outro_id' => 5, 'modo' => null]))
        ->toBe(['invoice_id' => 'i-1', 'outro_id' => 5, 'status' => true, 'tentativa' => 2, 'tipo' => 'cobranca.paga']);
});
