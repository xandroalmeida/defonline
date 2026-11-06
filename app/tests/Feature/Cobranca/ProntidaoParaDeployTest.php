<?php

declare(strict_types=1);

use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Infrastructure\Cobranca\Nenhum\ProvedorNaoConfigurado;
use App\Livewire\Backoffice\Planos;
use App\Models\CobrancaConfig;
use App\Models\ProviderEvent;
use App\Models\Usuario;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * STORY-093 CA-1 (D-10, cenário E22) — produção com a cobrança desligada e sem PAYMENTS_PROVIDER: o beta roda como
 * antes; agendadores saem com sucesso sem agir, o webhook não dá 500 e o interruptor não liga.
 */
function _producaoSemProvedor(?string $provider = null, array $extra = []): void
{
    app()->detectEnvironment(fn () => 'production');
    config(['services.payments.provider' => $provider, 'services.asaas.base_url' => null, 'services.asaas.api_key' => null, 'services.asaas.webhook_token' => null, ...$extra]);
    app()->forgetInstance(PaymentProvider::class);
}

afterEach(function () {
    app()->detectEnvironment(fn () => 'testing');
    app()->forgetInstance(PaymentProvider::class);
});

describe('CA-1 — sem provedor, em produção, com a cobrança desligada', function () {
    it('todos os agendadores de cobrança saem com sucesso sem agir', function () {
        _producaoSemProvedor();
        expect(CobrancaConfig::ligada())->toBeFalse();
        $comandos = collect(Schedule::events())
            ->map(fn ($e) => trim(explode(' ', str_replace(["'", '"'], '', (string) $e->command), 3)[2] ?? ''))
            ->filter(fn (string $c) => str_starts_with($c, 'cobranca:') || str_starts_with($c, 'assinaturas:') || str_starts_with($c, 'cupons:') || str_starts_with($c, 'trial:'))
            ->unique()->values();

        expect($comandos->map(fn (string $c) => Str::before($c, ' '))->all())
            ->toContain('cobranca:regua', 'cobranca:conciliar', 'assinaturas:cancelar-antigas', 'assinaturas:ajustar-no-provedor', 'assinaturas:ciclos-gratuitos');

        foreach ($comandos as $comando) {
            expect(Artisan::call($comando))->toBe(0, "{$comando} saiu com erro sem provedor");
        }
    });

    it('o webhook responde 401, e não 500, e não grava nada', function () {
        _producaoSemProvedor();

        _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_prod', ['payment' => ['id' => 'pay_1']])->assertUnauthorized();

        expect(ProviderEvent::query()->count())->toBe(0);
    });

    it('Asaas sem URL, chave ou token também não derruba: entra sem provedor, com o motivo', function () {
        _producaoSemProvedor('asaas');

        expect(app(PaymentProvider::class))->toBeInstanceOf(ProvedorNaoConfigurado::class)
            ->and(ProvedorNaoConfigurado::motivo())->toContain('ASAAS_BASE_URL')
            ->and(Artisan::call('cobranca:regua'))->toBe(0);
        _postarWebhookAsaas($this, 'PAYMENT_CONFIRMED', 'evt_prod2')->assertUnauthorized();
    });

    it('valor explícito errado em produção (fake, nome desconhecido) continua estourando: é erro de deploy', function (string $provider, string $trecho) {
        _producaoSemProvedor($provider);

        expect(fn () => app(PaymentProvider::class))->toThrow(InvalidArgumentException::class, $trecho);
    })->with([
        'fake em produção' => ['fake', 'produção'],
        'nome desconhecido' => ['asass', 'desconhecido'],
    ]);

    it('qualquer operação real falha com o motivo, nunca finge', function () {
        _producaoSemProvedor();
        $p = app(PaymentProvider::class);

        expect($p->nome())->toBe('nenhum');
        expect(fn () => $p->getCharge(new ReferenciaProvedor('nenhum', 'x')))->toThrow(ProvedorIndisponivel::class, 'PAYMENTS_PROVIDER');
    });

    it('com Asaas configurado em produção, nada muda: o provedor é o Asaas', function () {
        _producaoSemProvedor('asaas', ['services.asaas.base_url' => 'https://api.asaas.com/v3', 'services.asaas.api_key' => '$aact_prod_x', 'services.asaas.webhook_token' => str_repeat('t', 40)]);

        expect(app(PaymentProvider::class)->nome())->toBe('asaas')->and(ProvedorNaoConfigurado::ativo())->toBeFalse();
    });

    it('o interruptor recusa ligar e explica o motivo; desligar sempre pode', function () {
        _producaoSemProvedor();
        $admin = Usuario::factory()->create();
        $admin->forceFill(['permissoes_admin' => ['planos.gerenciar']])->save();

        $tela = Livewire::actingAs($admin)->test(Planos::class)->set('cobrancaAtiva', true)->call('salvarCobranca');

        $tela->assertSet('cobrancaAtiva', false)->assertSee('Não dá para ligar a cobrança')->assertSee('PAYMENTS_PROVIDER');
        expect(CobrancaConfig::ligada())->toBeFalse();

        CobrancaConfig::atual()->forceFill(['ativa' => true])->save();
        Livewire::actingAs($admin)->test(Planos::class)->set('cobrancaAtiva', false)->call('salvarCobranca');
        expect(CobrancaConfig::ligada())->toBeFalse();
    });

    it('com a cobrança ligada e sem provedor os agendadores NÃO se calam: seguem e falham alto', function () {
        _producaoSemProvedor();
        CobrancaConfig::atual()->forceFill(['ativa' => true])->save();

        // Nada pendente: roda de verdade (não pula), sem ter o que pedir ao provedor.
        $this->artisan('assinaturas:cancelar-antigas')->expectsOutputToContain('Assinaturas antigas canceladas: 0')->assertSuccessful();
    });
});
