<?php

declare(strict_types=1);

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\WebhookBruto;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Invoice;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\Usuario;
use App\Services\Cobranca\IniciarAssinatura;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use App\Services\Cobranca\ReceberWebhook;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Troca o provedor ativo (o container guarda o provedor como singleton). */
function _usarProvedor(string $nome, array $config = []): void
{
    config(['services.payments.provider' => $nome] + $config);
    app()->forgetInstance(PaymentProvider::class);
}

function _configAsaasDeTeste(): void
{
    config([
        'services.asaas.base_url' => 'https://api-sandbox.asaas.com/v3',
        'services.asaas.api_key' => '$aact_hmlg_chave-de-teste',
        'services.asaas.webhook_token' => 'tok-asaas-de-teste-aleatorio-com-32-caracteres',
    ]);
    _usarProvedor('asaas');
}

function _pacote(string $codigo = 'plus'): PacoteCredito
{
    return PacoteCredito::query()->where('codigo', $codigo)->firstOrFail();
}

function _compradorConfirmado(): Usuario
{
    CobrancaConfig::atual()->update(['ativa' => true]);

    return Usuario::factory()->create(['email_confirmed_at' => now()]);
}

/**
 * Asaas simulado: `POST /checkouts` abre a sessão `chk-1` e `GET /payments/{id}` devolve o que o teste
 * puser em `$cobrancas[id]`. Chame `_asaasSimulado()` de novo para zerar.
 */
final class AsaasSimulado
{
    /** @var array<string, array<string, mixed>> */
    public static array $cobrancas = [];

    public static int $checkouts = 0;
}

function _asaasSimulado(): void
{
    AsaasSimulado::$cobrancas = [];
    AsaasSimulado::$checkouts = 0;
    _configAsaasDeTeste();
    Mail::fake();

    Http::preventStrayRequests();
    Http::fake(function (Request $r) {
        if ($r->method() === 'POST' && str_ends_with($r->url(), '/checkouts')) {
            $n = ++AsaasSimulado::$checkouts;

            return Http::response(['id' => "chk-{$n}", 'link' => "https://sandbox.asaas.com/checkoutSession/show/chk-{$n}"], 200);
        }

        if ($r->method() === 'POST' && str_contains($r->url(), '/cancel')) {
            return Http::response(['status' => 'CANCELED'], 200);
        }

        if ($r->method() === 'GET' && preg_match('#/payments/([^/]+)$#', $r->url(), $m) === 1) {
            return isset(AsaasSimulado::$cobrancas[$m[1]])
                ? Http::response(AsaasSimulado::$cobrancas[$m[1]], 200)
                : Http::response(['errors' => [['code' => 'not_found']]], 404);
        }

        return Http::response('', 500);
    });
}

/** Registra no Asaas simulado a cobrança que o webhook vai citar. */
function _asaasCobranca(string $id, Invoice $invoice, string $status = 'RECEIVED', ?int $valorCentavos = null, bool $comReferencia = true): void
{
    AsaasSimulado::$cobrancas[$id] = [
        'id' => $id, 'status' => $status, 'deleted' => false,
        'value' => ($valorCentavos ?? $invoice->valor_centavos) / 100,
        'checkoutSession' => $invoice->checkout_id_externo,
        'externalReference' => $comReferencia ? $invoice->id : null,
        'paymentDate' => '2026-11-05',
    ];
}

/** POST do webhook no formato do Asaas, assinado com o token configurado. */
function _postarWebhookAsaas(TestCase $t, string $evento, string $eventoId, array $extra = [], ?string $token = 'tok-asaas-de-teste-aleatorio-com-32-caracteres'): TestResponse
{
    return $t->postJson('/webhooks/pagamentos', ['id' => $eventoId, 'event' => $evento, ...$extra], $token === null ? [] : ['asaas-access-token' => $token]);
}

function _compraPendenteAsaas(Usuario $usuario, ?PacoteCredito $pacote = null): Invoice
{
    return app(IniciarCompraDeCreditos::class)->executar($usuario, $pacote ?? _pacote(), '203.0.113.7', 'PestAgent');
}

/** Dados de sessão de um "Logar como" ativo, para `withSession()`. */
function _sessaoDePersonificacao(Usuario $admin, Usuario $alvo): array
{
    return ['personificacao' => [
        'sessao_id' => (string) Str::uuid7(), 'admin_id' => $admin->id,
        'usuario_id' => $alvo->id, 'iniciada_em' => now()->toIso8601String(),
    ]];
}

/** "Logar como" para chamadas diretas ao serviço: o alvo autenticado e a sessão da requisição atual com a marca. */
function _personificar(Usuario $admin, Usuario $alvo): void
{
    Auth::login($alvo);
    $sessao = app('session.store');
    $sessao->start();

    foreach (_sessaoDePersonificacao($admin, $alvo) as $chave => $valor) {
        $sessao->put($chave, $valor);
    }

    request()->setLaravelSession($sessao);
}

/** Admin da equipe, único que pode entrar em "Logar como". */
function _adminDaEquipe(): Usuario
{
    config(['backoffice.equipe_emails' => ['equipe@ebparcerias.com.br']]);

    return Usuario::factory()->create(['email' => 'equipe@ebparcerias.com.br']);
}

// STORY-077 — ajudantes da assinatura com cartão.
function _plano(string $nome = 'Básico'): Plano
{
    return Plano::query()->where('nome', $nome)->firstOrFail();
}

function _assinaturaPendente(Usuario $usuario, ?Plano $plano = null, CicloCobranca $ciclo = CicloCobranca::Mensal, MeioPagamento $meio = MeioPagamento::Cartao): Invoice
{
    return app(IniciarAssinatura::class)->executar($usuario, $plano ?? _plano(), $ciclo, '203.0.113.7', 'PestAgent', $meio);
}

function _fake(): FakePaymentProvider
{
    $p = app(PaymentProvider::class);
    assert($p instanceof FakePaymentProvider);

    return $p;
}

/** Entrega o webhook ao app (a fila é síncrona nos testes). */
function _entregar(WebhookBruto $webhook): string
{
    return app(ReceberWebhook::class)->tratar($webhook);
}

/** O banco aprova o cartão no checkout fake: devolve a Invoice atualizada. */
function _pagarAssinatura(Invoice $invoice): Invoice
{
    _entregar(_fake()->simularPagamento((string) $invoice->checkout_id_externo));

    return $invoice->fresh();
}

/** STORY-080 — plano anual pago com Pix já ativo (checkout fake pago). */
function _assinaturaPixAtiva(Usuario $usuario, ?Plano $plano = null): Assinatura
{
    $invoice = _pagarAssinatura(_assinaturaPendente($usuario, $plano, CicloCobranca::Anual, MeioPagamento::Pix));

    return Assinatura::query()->findOrFail($invoice->assinatura_id);
}
