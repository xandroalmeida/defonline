<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\Origem;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\TipoEvento;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Livewire\Cobranca\MeuPlano;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\ConsumoAnalise;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Invoice;
use App\Models\TrialConfig;
use App\Models\TrocaDeCartao;
use App\Models\Usuario;
use App\Services\Cobranca\GerirRenovacao;
use App\Services\Cobranca\TrocarCartao;
use App\Support\Cobranca\HistoricoDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Js;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * STORY-081 — tela Meu plano (spec STORY-070-meu-plano, DDR-009): leitura de plano, cota, créditos e
 * cobranças, e as ações de trocar cartão, cancelar e reativar a renovação, com o provedor fake.
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->usuario = _compradorConfirmado();
});

function _dia(DateTimeInterface $d): string
{
    return CarbonImmutable::instance($d)->setTimezone('America/Sao_Paulo')->format('d/m/Y');
}

function _meuPlano(Usuario $u): Testable
{
    return Livewire::actingAs($u)->test(MeuPlano::class);
}

function _mpAssinar(Usuario $u): Assinatura
{
    _pagarAssinatura(_assinaturaPendente($u));

    return Assinatura::query()->where('usuario_id', $u->id)->sole();
}

describe('acesso e interruptor (CA-10)', function () {
    it('com a cobrança desligada a rota é 404 e o item de menu não existe', function () {
        CobrancaConfig::atual()->update(['ativa' => false]);

        $this->actingAs($this->usuario)->get(route('meu-plano'))->assertNotFound();
        $this->actingAs($this->usuario)->get(route('home'))->assertOk()->assertDontSee('app-nav-meu-plano', false);
    });

    it('exige login', function () {
        $this->get(route('meu-plano'))->assertRedirect(route('login'));
    });

    it('com a cobrança ligada abre, e o menu tem "Meu plano" depois de "Planos"', function () {
        $this->actingAs($this->usuario)->get(route('meu-plano'))->assertOk()->assertSee('Meu plano');
        $this->actingAs($this->usuario)->get(route('home'))->assertOk()->assertSee('app-nav-meu-plano', false);
    });
});

describe('CA-7 oferta de planos', function () {
    it('quem não tem plano nem créditos vê a oferta, com planos, pacotes e teste', function () {
        TrialConfig::atual()->update(['ativo' => true]);

        _meuPlano($this->usuario)
            ->assertSee('Você ainda não tem um plano')
            ->assertSeeHtml('screen-meuplano-oferta-plano')
            ->assertSeeHtml('screen-meuplano-oferta-pacote')
            ->assertSee('Começar teste grátis')
            ->assertSee('Você ainda não fez nenhuma compra.')
            ->assertDontSee('Cancelar renovação');
    });

    it('sem teste elegível a oferta não promete teste', function () {
        TrialConfig::atual()->update(['ativo' => false]);

        _meuPlano($this->usuario)->assertSee('Você ainda não tem um plano')->assertDontSee('Começar teste grátis');
    });

    it('só com créditos: sem plano, usa créditos avulsos, lista o lote e avisa que o Raio X não está incluído', function () {
        app(Carteira::class)->concederCreditos($this->usuario->id, 3, now()->addMonths(6));

        _meuPlano($this->usuario)
            ->assertDontSee('Você ainda não tem um plano')
            ->assertSee('Sem plano. Você usa créditos avulsos.')
            ->assertSee('3 no total')
            ->assertSee('O Raio X do Negócio não está incluído nos créditos.')
            ->assertSee('Assinar para ter o Raio X');
    });
});

describe('CA-1 plano, ciclo, renovação, cota e lotes', function () {
    it('mostra plano, ciclo, valor, próxima renovação, cartão e a cota restante', function () {
        $a = _mpAssinar($this->usuario);
        $a->forceFill(['cota_restante_ciclo' => 0])->save();
        $plano = _plano();

        _meuPlano($this->usuario)
            ->assertSee($plano->nome.' · mensal')
            ->assertSeeHtml('screen-meuplano-situacao')
            ->assertSee('Ativo')
            ->assertSeeText('R$ '.number_format($plano->preco_mensal_centavos / 100, 2, ',', '.').' por mês')
            ->assertSee('renova em '._dia($a->proximo_vencimento))
            ->assertSee('Visa final 4242')
            ->assertSee('0 de '.$plano->cota_mensal.($plano->cota_mensal === 1 ? ' restante' : ' restantes').' neste ciclo')
            ->assertSee('Renova em '._dia($a->proximo_vencimento).' (não acumula)')
            ->assertSee('O Raio X do Negócio está incluído no plano.')
            ->assertSee('Trocar cartão')
            ->assertSee('Cancelar renovação');
    });

    it('no anual a cota renova no aniversário mensal, não no fim do ano', function () {
        _pagarAssinatura(_assinaturaPendente($this->usuario, null, CicloCobranca::Anual));
        $a = Assinatura::query()->sole();

        _meuPlano($this->usuario)
            ->assertSee('anual')
            ->assertSee('por ano')
            ->assertSee('Renova em '._dia($a->proxima_cota_em).' (não acumula)');
    });

    it('lista os lotes com créditos e vencimento, e alerta o que vence em breve (um aviso por vez)', function () {
        $c = app(Carteira::class);
        $c->concederCreditos($this->usuario->id, 2, now()->addDays(10));
        $c->concederCreditos($this->usuario->id, 1, now()->addMonths(10));

        $t = _meuPlano($this->usuario)
            ->assertSee('3 no total')
            ->assertSee('2 — vencem em '._dia(now()->addDays(10)))
            ->assertSee('1 — vencem em '._dia(now()->addMonths(10)))
            ->assertSeeHtml('screen-meuplano-lote-perto')
            ->assertSeeHtml('screen-meuplano-aviso')
            ->assertSee('Você tem créditos que vencem em breve.');

        expect(substr_count($t->html(), 'screen-meuplano-lote-perto'))->toBe(1);
    });

    it('lote vencido ou esgotado não aparece, e o limiar vem da configuração', function () {
        $c = app(Carteira::class);
        $c->concederCreditos($this->usuario->id, 1, now()->addDays(40));
        config(['services.payments.lote_perto_de_vencer_dias' => 60]);

        _meuPlano($this->usuario)->assertSeeHtml('screen-meuplano-lote-perto');

        config(['services.payments.lote_perto_de_vencer_dias' => 30]);
        _meuPlano($this->usuario)->assertDontSeeHtml('screen-meuplano-lote-perto')->assertDontSeeHtml('screen-meuplano-aviso');
    });
});

describe('CA-2 cobranças', function () {
    it('lista data, descrição, valor, desconto, meio e situação da cobrança paga', function () {
        $invoice = _pagarAssinatura(_assinaturaPendente($this->usuario));

        _meuPlano($this->usuario)
            ->assertSeeHtml('screen-meuplano-cobranca')
            ->assertSee($invoice->descricao)
            ->assertSee('R$ '.number_format($invoice->valor_centavos / 100, 2, ',', '.'))
            ->assertSee(_dia($invoice->pago_em))
            ->assertSee('Cartão')
            ->assertSee('Pago')
            ->assertSee('—');
    });

    it('mostra recusada e reembolsada, e esconde o que nunca virou cobrança', function () {
        $recusada = _assinaturaPendente($this->usuario);
        $recusada->forceFill(['status' => EstadoCobranca::Falhou, 'descricao' => 'Cobrança recusada X'])->save();
        $pago = _pagarAssinatura(_assinaturaPendente($this->usuario));
        $estornada = Invoice::query()->create([...$pago->only(['usuario_id', 'tipo', 'valor_centavos', 'meio', 'provedor']), 'descricao' => 'Cobrança estornada Y', 'status' => EstadoCobranca::Estornada]);
        Invoice::query()->create([...$pago->only(['usuario_id', 'tipo', 'valor_centavos', 'meio', 'provedor']), 'descricao' => 'Checkout abandonado Z', 'status' => EstadoCobranca::Cancelada]);
        Invoice::query()->create([...$pago->only(['usuario_id', 'tipo', 'valor_centavos', 'meio', 'provedor']), 'descricao' => 'Falha nossa W', 'status' => EstadoCobranca::Falhou, 'motivo_encerramento' => 'provedor']);
        Invoice::query()->create([...$pago->only(['usuario_id', 'tipo', 'valor_centavos', 'meio', 'provedor']), 'descricao' => 'Pix aguardando V', 'meio' => 'pix', 'status' => EstadoCobranca::Pendente, 'checkout_expira_em' => now()->addMinutes(20)]);
        Invoice::query()->create([...$pago->only(['usuario_id', 'tipo', 'valor_centavos', 'meio', 'provedor']), 'descricao' => 'Pix expirado U', 'status' => EstadoCobranca::Pendente, 'checkout_expira_em' => now()->subMinute()]);
        Invoice::query()->create([...$pago->only(['usuario_id', 'tipo', 'valor_centavos', 'meio', 'provedor']), 'descricao' => 'Cobrança contestada T', 'status' => EstadoCobranca::Contestada]);

        _meuPlano($this->usuario)
            ->assertSee('Cobrança recusada X')->assertSee('Recusado')
            ->assertSee('Cobrança estornada Y')->assertSee('Reembolsado')
            ->assertSee('Pix aguardando V')->assertSee('Pendente')->assertSee('Pix')
            ->assertSee('Cobrança contestada T')->assertSee('Contestado')
            ->assertDontSee('Checkout abandonado Z')
            ->assertDontSee('Falha nossa W')
            ->assertDontSee('Pix expirado U');
    });

    it('não mostra cobranças de outro usuário', function () {
        _pagarAssinatura(_assinaturaPendente(_compradorConfirmado()));

        _meuPlano($this->usuario)->assertSee('Você ainda não fez nenhuma compra.');
    });

    it('se só as cobranças falham, plano e análises aparecem e dá para tentar de novo', function () {
        _mpAssinar($this->usuario);
        app()->bind(HistoricoDeCobrancas::class, fn () => new class extends HistoricoDeCobrancas
        {
            public function do(string $usuarioId): array
            {
                throw new RuntimeException('banco fora');
            }
        });

        $t = _meuPlano($this->usuario)
            ->assertSee('Não conseguimos carregar as cobranças agora.')
            ->assertSee('Cancelar renovação')
            ->assertSee('Análises');

        app()->bind(HistoricoDeCobrancas::class, fn () => new HistoricoDeCobrancas);
        $t->call('recarregar')->assertDontSee('Não conseguimos carregar as cobranças agora.')->assertSeeHtml('screen-meuplano-cobranca');
    });
});

describe('CA-4 cancelar a renovação', function () {
    it('cancela, diz até quando vai o acesso e troca o botão por "Reativar renovação"', function () {
        $a = _mpAssinar($this->usuario);
        $data = _dia($a->proximo_vencimento);

        _meuPlano($this->usuario)
            ->call('cancelarRenovacao')
            ->assertSee("Renovação cancelada. Seu acesso continua até {$data}.")
            ->assertSee('Cancelado')
            ->assertSee("Você cancelou a renovação. Seu acesso continua até {$data}. Não haverá nova cobrança.")
            ->assertSee('Reativar renovação')
            ->assertDontSee('Trocar cartão')
            ->assertDontSeeHtml('screen-meuplano-cancelar')
            ->assertSee('Renova' === 'x' ? '' : "Acesso até {$data} (não acumula)");

        expect($a->refresh()->status)->toBe(StatusAssinatura::Cancelada);
    });

    it('o botão abre o diálogo com título, aviso do fim do acesso e botões Voltar e Cancelar renovação', function () {
        $a = _mpAssinar($this->usuario);
        $a->forceFill(['data_inicio' => now()->subDays(8)])->save();
        $html = _meuPlano($this->usuario)->html();

        $mensagem = 'Você continua com acesso até '._dia($a->proximo_vencimento).' e não será cobrado de novo. Dá para reativar até lá.';

        expect($html)->toContain(Js::from('Cancelar a renovação?')->toHtml())
            ->and($html)->toContain(Js::from($mensagem)->toHtml())
            ->and($html)->toContain("voltar: 'Voltar'");
    });

    it('em atraso: o botão existe, o diálogo diz que o acesso acaba agora, e cancelar encerra o plano e preserva o histórico', function () {
        $a = _mpAssinar($this->usuario);
        $a->forceFill(['status' => StatusAssinatura::EmAtraso])->save();
        $data = _dia($a->proximo_vencimento);
        $mensagem = "A renovação será cancelada e nada mais será cobrado. Como o ciclo de {$data} não foi pago, o acesso é encerrado agora, não no fim do ciclo. Seu histórico de cobranças e diagnósticos continua disponível.";

        $t = _meuPlano($this->usuario);
        $html = $t->html();
        expect($html)->toContain('screen-meuplano-cancelar')
            ->and($html)->toContain(Js::from($mensagem)->toHtml())
            ->and($html)->toContain(Js::from('Cancelar a renovação?')->toHtml());

        $t->call('cancelarRenovacao')
            ->assertSee("Renovação cancelada. Como o ciclo de {$data} não foi pago, o acesso foi encerrado agora e nada mais será cobrado. Seu histórico continua disponível.")
            ->assertSee('Encerrado')
            ->assertSee("Seu plano terminou em {$data}.")
            ->assertSee('Plano Básico')
            ->assertDontSeeHtml('screen-meuplano-cancelar');

        expect($a->refresh()->status)->toBe(StatusAssinatura::Expirada)->and(Invoice::query()->where('status', 'paga')->count())->toBe(1);
    });

    it('em atraso, com o provedor fora do ar, nada muda e a tela diz que a renovação continua em atraso', function () {
        $a = _mpAssinar($this->usuario);
        $a->forceFill(['status' => StatusAssinatura::EmAtraso])->save();
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();

        _meuPlano($this->usuario)
            ->call('cancelarRenovacao')
            ->assertSee('Não conseguimos cancelar agora. Nada foi alterado: a renovação continua em atraso. Tente de novo.')
            ->assertSee('Em atraso');

        expect($a->refresh()->status)->toBe(StatusAssinatura::EmAtraso);
    });

    it('com o provedor fora do ar diz o estado real: a renovação continua ativa', function () {
        $a = _mpAssinar($this->usuario);
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();

        _meuPlano($this->usuario)
            ->call('cancelarRenovacao')
            ->assertSee('Não conseguimos cancelar agora. Sua renovação continua ativa. Tente de novo.')
            ->assertSee('Cancelar renovação');

        expect($a->refresh()->status)->toBe(StatusAssinatura::Ativa);
    });

    it('sem assinatura a ação é negada com uma explicação, sem erro', function () {
        _meuPlano($this->usuario)->call('cancelarRenovacao')->assertSee('Não encontramos um plano para alterar.');
    });
});

describe('CA-5 reativar a renovação', function () {
    it('reativa e informa a próxima cobrança', function () {
        $a = _mpAssinar($this->usuario);
        app(GerirRenovacao::class)->cancelar($this->usuario);

        _meuPlano($this->usuario)
            ->call('reativarRenovacao')
            ->assertSee('Renovação reativada. Próxima cobrança em '._dia($a->proximo_vencimento).'.')
            ->assertSee('Ativo')
            ->assertSee('Cancelar renovação');

        expect($a->refresh()->status)->toBe(StatusAssinatura::Ativa);
    });

    it('com o provedor fora do ar continua cancelada', function () {
        _mpAssinar($this->usuario);
        app(GerirRenovacao::class)->cancelar($this->usuario);
        FakePaymentProvider::falharProximaOperacaoDeAssinatura();

        _meuPlano($this->usuario)->call('reativarRenovacao')->assertSee('Não conseguimos reativar agora. Sua renovação continua cancelada.');
    });

    it('depois do fim do ciclo a tela mostra Encerrado, sem botão de reativar', function () {
        $a = _mpAssinar($this->usuario);
        app(GerirRenovacao::class)->cancelar($this->usuario);
        $a->refresh()->forceFill(['proximo_vencimento' => now()->subDays(2)])->save();

        _meuPlano($this->usuario)
            ->assertSee('Encerrado')
            ->assertSee('Seu plano terminou em '._dia(now()->subDays(2)).'. Seus diagnósticos continuam disponíveis.')
            ->assertSee('Assinar um plano')
            ->assertDontSee('Reativar renovação');
    });
});

describe('CA-3 trocar o cartão', function () {
    it('abre a seção, explica e leva ao checkout do cartão novo', function () {
        $a = _mpAssinar($this->usuario);

        $t = _meuPlano($this->usuario)
            ->call('abrirTrocaDeCartao')
            ->assertSee('O cartão atual só é desativado depois que o novo for confirmado')
            ->assertSee('a próxima cobrança continua em '._dia($a->proximo_vencimento))
            ->assertSee('Salvar cartão');

        $t->call('trocarCartao')->assertRedirectContains('/pagamentos/fake/troca/');
        expect(TrocaDeCartao::query()->sole()->assinatura_id)->toBe($a->id);

        $t->call('fecharTrocaDeCartao')->assertDontSee('Salvar cartão');
    });

    it('em atraso: aviso de alerta, "Trocar cartão" como ação principal e "Salvar e cobrar agora"', function () {
        $a = _mpAssinar($this->usuario);
        $a->forceFill(['status' => StatusAssinatura::EmAtraso])->save();

        _meuPlano($this->usuario)
            ->assertSee('Em atraso')
            ->assertSee('Não conseguimos cobrar a renovação de '._dia($a->proximo_vencimento).'. Atualize o cartão para manter o acesso.')
            ->assertSee('Cancelar renovação')
            ->call('abrirTrocaDeCartao')
            ->assertSee('Salvar e cobrar agora')
            ->assertSee('a renovação em atraso é cobrada no cartão novo');
    });

    it('provedor fora do ar: o cartão atual continua valendo e a tela diz isso', function () {
        _mpAssinar($this->usuario);
        FakePaymentProvider::falharProximoCheckout();

        _meuPlano($this->usuario)->call('abrirTrocaDeCartao')->call('trocarCartao')
            ->assertSee('O cartão atual continua valendo.')
            ->assertNoRedirect();
    });

    it('ao voltar do checkout, aguarda a confirmação e depois mostra o cartão atualizado', function () {
        $a = _mpAssinar($this->usuario);
        $troca = app(TrocarCartao::class)->iniciar($this->usuario);

        $t = Livewire::withQueryParams(['cartao' => 'retorno'])->actingAs($this->usuario)->test(MeuPlano::class)
            ->assertSee('Estamos confirmando o seu novo cartão…');

        _entregar(_fake()->webhook(TipoEvento::CheckoutConcluido, (string) $troca->checkout_id_externo));

        $t->call('recarregar')->assertDontSee('Estamos confirmando')->assertSee('Cartão atualizado.');
        expect($a->refresh()->id_externo)->not->toBe($troca->antiga_id_externo);
    });

    it('ao voltar sem concluir (recusa ou prazo), diz que o cartão anterior continua valendo', function () {
        _mpAssinar($this->usuario);
        $troca = app(TrocarCartao::class)->iniciar($this->usuario);
        _entregar(_fake()->simularRecusa((string) $troca->checkout_id_externo));

        Livewire::withQueryParams(['cartao' => 'retorno'])->actingAs($this->usuario)->test(MeuPlano::class)
            ->assertSee('Não conseguimos confirmar o novo cartão. O cartão anterior continua valendo.');
    });
});

describe('situações do plano (DDR-009)', function () {
    it('teste: pill Teste, fim e análises, sem cancelar', function () {
        app(Carteira::class)->concederCota($this->usuario->id, 1, now(), now()->addDays(5), null, null, true);

        _meuPlano($this->usuario)
            ->assertSee('Teste gratuito')
            ->assertSee('Teste')
            ->assertSee('Termina em '._dia(now()->addDays(5)).' · 1 análise')
            ->assertSee('Assinar um plano')
            ->assertDontSee('Cancelar renovação');
    });

    it('plano sem recorrência no provedor (Pix anual): diz até quando vai e não tem cancelar nem trocar cartão', function () {
        $a = _mpAssinar($this->usuario);
        $a->forceFill(['id_externo' => null, 'cartao_ultimos4' => null])->save();

        _meuPlano($this->usuario)
            ->assertSee('Este plano não renova sozinho. Termina em '._dia($a->proximo_vencimento).'.')
            ->assertDontSee('Cancelar renovação')
            ->assertDontSee('Trocar cartão');
    });

    it('as ações recusam o plano sem recorrência com uma explicação', function () {
        $a = _mpAssinar($this->usuario);
        $a->forceFill(['id_externo' => null])->save();

        _meuPlano($this->usuario)->call('cancelarRenovacao')->assertSee('Este plano não renova sozinho')
            ->call('trocarCartao')->assertSee('Este plano não renova sozinho');
    });

    it('troca de plano só aparece quando a tela de troca existir (STORY-082)', function () {
        _mpAssinar($this->usuario);
        _meuPlano($this->usuario)->assertDontSeeHtml('screen-meuplano-trocar-plano');

        Route::get('/planos/trocar/{plano}/{ciclo}', fn () => 'ok')->name('plano.trocar');
        app('router')->getRoutes()->refreshNameLookups();

        _meuPlano($this->usuario)->assertSeeHtml('screen-meuplano-trocar-plano');
    });
});

describe('CA-6 arrependimento', function () {
    it('o texto de apoio com o e-mail está sempre na tela', function () {
        _meuPlano($this->usuario)
            ->assertSee('Arrependimento: em até 7 dias da contratação, se você ainda não fez nenhuma análise, peça o reembolso em')
            ->assertSee('eb@ebparcerias.com');
    });

    it('nos 7 dias e sem análise o diálogo de cancelamento diz que dá para pedir o reembolso integral', function () {
        $a = _mpAssinar($this->usuario);

        $html = _meuPlano($this->usuario)->html();

        expect($html)->toContain(Js::from('Você continua com acesso até '._dia($a->proximo_vencimento).' e não será cobrado de novo. Dá para reativar até lá. Você contratou hoje e ainda não fez nenhuma análise: dá para pedir o reembolso integral em eb@ebparcerias.com.')->toHtml());
    });

    it('passados os 7 dias, o diálogo não oferece reembolso', function () {
        $a = _mpAssinar($this->usuario);
        $a->forceFill(['data_inicio' => now()->subDays(8)])->save();

        expect(_meuPlano($this->usuario)->html())->not->toContain('dá para pedir o reembolso integral');
    });

    it('com análise feita, o diálogo não oferece reembolso', function () {
        $a = _mpAssinar($this->usuario);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
        $diagnostico = Diagnostico::factory()->create(['usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $empresa->id]);
        ConsumoAnalise::query()->create([
            'usuario_id' => $this->usuario->id, 'diagnostico_id' => $diagnostico->id, 'empresa_analisada_id' => $empresa->id,
            'origem' => Origem::Cota, 'assinatura_id' => $a->id, 'consumido_em' => now(),
        ]);

        expect(_meuPlano($this->usuario)->html())->not->toContain('dá para pedir o reembolso integral');
    });

    it('três dias depois, diz há quantos dias', function () {
        $a = _mpAssinar($this->usuario);
        $a->forceFill(['data_inicio' => now()->subDays(3)])->save();

        expect(_meuPlano($this->usuario)->html())->toContain('Você contratou há 3 dias e ainda não fez nenhuma análise');
    });
});

describe('CA-8 "Logar como"', function () {
    it('o admin vê a tela inteira, com as ações desabilitadas e o aviso de somente leitura', function () {
        $a = _mpAssinar($this->usuario);
        $a->forceFill(['status' => StatusAssinatura::Ativa])->save();
        $admin = _adminDaEquipe();

        $resposta = $this->withSession(_sessaoDePersonificacao($admin, $this->usuario))->actingAs($this->usuario)->get(route('meu-plano'))->assertOk()
            ->assertSee('Você está acessando como administrador. Aqui é só leitura.')
            ->assertSee('Cancelar renovação')
            ->assertSee('Trocar cartão')
            ->assertSee(_plano()->nome);

        expect($resposta->getContent())->toMatch('/<button[^>]*disabled[^>]*dusk="screen-meuplano-cancelar"/s');
    });
});
