<?php

declare(strict_types=1);

use App\Domain\Cobranca\Bloqueio;
use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Motor\FonteRecomendacoes;
use App\Livewire\Diagnostico\Quiz;
use App\Models\CobrancaConfig;
use App\Models\ConsumoAnalise;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\QuizRascunho;
use App\Models\Usuario;
use App\Support\Cobranca\TextoBloqueio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * STORY-073 CA-3, CA-5, CA-6, CA-9 e CA-11 — o quiz com a cobrança ligada: bloqueio no lugar do
 * fluxo (spec STORY-070-bloqueio-sem-saldo), rascunho preservado, e o fluxo de sempre desligado.
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
});

/** Preenche o quiz inteiro, parando no bloco 4 pronto para concluir. */
function _quizAteOFim(object $teste): Testable
{
    return Livewire::test(Quiz::class, ['empresa' => $teste->empresa])
        ->call('proximoBloco')
        ->set('Q08', '50000')->set('Q09', '100000')->set('Q14', '20000')->set('Q15', '8000')->set('Q16', '2000')
        ->set('Q10', '90')->set('Q11', '15')->set('Q12', '15')->set('Q13', '2')
        ->call('proximoBloco')
        ->set('Q02', '50000')->set('Q03', '20000')->set('Q04', '10000')
        ->set('Q05', '300000')->set('Q06', '40000')->set('Q07', '80000')
        ->call('proximoBloco')
        ->set('Q17', 'nao');
}

function _ligarCobrancaQuiz(): void
{
    CobrancaConfig::atual()->update(['ativa' => true]);
}

describe('interruptor desligado', function () {
    it('abre o quiz e conclui como hoje, sem saldo e sem registrar consumo', function () {
        Livewire::test(Quiz::class, ['empresa' => $this->empresa])
            ->assertSet('bloqueio_saldo', null)->assertSet('erro_carteira', false)->assertSee('Bloco 1 de 4');

        _quizAteOFim($this)->call('submeter')->assertHasNoErrors();

        expect(Diagnostico::query()->count())->toBe(1)->and(ConsumoAnalise::query()->count())->toBe(0);
    });
});

describe('CA-3 abertura sem saldo', function () {
    it('bloqueia no lugar do quiz, com título, causa e o acesso aos diagnósticos', function () {
        _ligarCobrancaQuiz();

        Livewire::test(Quiz::class, ['empresa' => $this->empresa])
            ->assertSee('Você ainda não tem análises')
            ->assertSee('Para fazer uma análise, comece o teste grátis ou escolha um plano.')
            ->assertSee('Seus diagnósticos anteriores continuam disponíveis.')
            ->assertSeeHtml('dusk="screen-bloqueio-ver-diagnosticos"')
            ->assertDontSee('Bloco 1 de 4')
            ->assertDontSeeHtml('dusk="screen-bloqueio-rascunho-salvo"');
    });

    it('com rascunho, diz que as respostas estão salvas e não apaga o rascunho', function () {
        $quiz = _quizAteOFim($this);
        expect(QuizRascunho::query()->count())->toBe(1);

        _ligarCobrancaQuiz();

        Livewire::test(Quiz::class, ['empresa' => $this->empresa])
            ->assertSeeHtml('dusk="screen-bloqueio-rascunho-salvo"')
            ->assertSee('Suas respostas estão salvas');

        expect(QuizRascunho::query()->count())->toBe(1);
    });

    it('com saldo, abre o quiz normalmente', function () {
        _ligarCobrancaQuiz();
        app(Carteira::class)->concederCota($this->usuario->id, 1, now()->subDay(), now()->addMonth());

        Livewire::test(Quiz::class, ['empresa' => $this->empresa])->assertSet('bloqueio_saldo', null)->assertSee('Bloco 1 de 4');
    });

    it('volta ao quiz, com o rascunho, depois que o saldo chega', function () {
        _quizAteOFim($this);
        _ligarCobrancaQuiz();
        Livewire::test(Quiz::class, ['empresa' => $this->empresa])->assertSee('Você ainda não tem análises');

        app(Carteira::class)->concederCreditos($this->usuario->id, 1, now()->addMonths(12));

        Livewire::test(Quiz::class, ['empresa' => $this->empresa])
            ->assertSet('bloqueio_saldo', null)->assertSet('bloco_atual', 4)->assertSee('Retomando seu rascunho');
    });

    it('mostra o título da causa real: teste usado, cota esgotada, atraso', function () {
        _ligarCobrancaQuiz();
        $assinatura = app(Carteira::class)->concederCota($this->usuario->id, 3, now()->subDay(), now()->addMonth());
        $assinatura->update(['cota_restante_ciclo' => 0]);

        Livewire::test(Quiz::class, ['empresa' => $this->empresa])
            ->assertSee('Você usou as análises deste ciclo')->assertSee('Suas 3 análises do ciclo acabaram. Novas análises em');

        app(Carteira::class)->mudarStatus($assinatura->id, StatusAssinatura::EmAtraso);

        Livewire::test(Quiz::class, ['empresa' => $this->empresa])
            ->assertSee('Sua assinatura está em atraso')->assertSee('Atualize o cartão');
    });
});

describe('CA-3 conclusão sem saldo', function () {
    it('se o saldo acabou entre abrir e concluir, bloqueia sem perder o rascunho nem gravar nada', function () {
        _ligarCobrancaQuiz();
        $assinatura = app(Carteira::class)->concederCota($this->usuario->id, 1, now()->subDay(), now()->addMonth());
        $quiz = _quizAteOFim($this);

        // Outra aba gasta a única análise.
        $assinatura->update(['cota_restante_ciclo' => 0]);

        $quiz->call('submeter')->assertNoRedirect()
            ->assertSee('Você usou as análises deste ciclo')
            ->assertSeeHtml('dusk="screen-bloqueio-rascunho-salvo"');

        $rascunho = QuizRascunho::query()->sole();
        expect(Diagnostico::query()->count())->toBe(0)
            ->and(ConsumoAnalise::query()->count())->toBe(0)
            ->and($rascunho->ultimo_bloco_preenchido)->toBe(4)
            ->and($rascunho->quiz_payload['Q17'])->toBe('nao')
            ->and($rascunho->quiz_payload['Q09'])->not->toBeNull();
    });

    it('com saldo, conclui, debita e vai ao diagnóstico', function () {
        _ligarCobrancaQuiz();
        $assinatura = app(Carteira::class)->concederCota($this->usuario->id, 2, now()->subDay(), now()->addMonth());

        $quiz = _quizAteOFim($this)->call('submeter')->assertHasNoErrors();

        $diagnostico = Diagnostico::query()->sole();
        $quiz->assertRedirect(route('diagnosticos.show', $diagnostico));
        expect($assinatura->fresh()->cota_restante_ciclo)->toBe(1)
            ->and(ConsumoAnalise::query()->sole()->diagnostico_id)->toBe($diagnostico->id)
            ->and(QuizRascunho::query()->count())->toBe(0);
    });
});

describe('CA-5 falha no cálculo', function () {
    it('não consome saldo, mostra o erro genérico e mantém o rascunho', function () {
        _ligarCobrancaQuiz();
        $assinatura = app(Carteira::class)->concederCota($this->usuario->id, 1, now()->subDay(), now()->addMonth());
        $quiz = _quizAteOFim($this);

        app()->bind(FonteRecomendacoes::class, fn () => new class implements FonteRecomendacoes
        {
            public function texto(string $setor, string $indicador, string $farol): ?string
            {
                throw new RuntimeException('matriz fora do ar');
            }

            public function versao(string $setor): string
            {
                throw new RuntimeException('matriz fora do ar');
            }
        });

        $quiz->call('submeter')->assertNoRedirect();

        expect($quiz->errors()->first('submeter'))->toContain('Não foi possível calcular o diagnóstico agora')
            ->and($assinatura->fresh()->cota_restante_ciclo)->toBe(1)
            ->and(ConsumoAnalise::query()->count())->toBe(0)
            ->and(Diagnostico::query()->count())->toBe(0)
            ->and(QuizRascunho::query()->count())->toBe(1);
    });
});

describe('CA-6 conclusão repetida', function () {
    it('outra aba concluiu o mesmo quiz: mostra "já concluída" com o link, sem cobrar de novo', function () {
        _ligarCobrancaQuiz();
        $assinatura = app(Carteira::class)->concederCota($this->usuario->id, 5, now()->subDay(), now()->addMonth());
        $abaA = _quizAteOFim($this);
        $abaB = Livewire::test(Quiz::class, ['empresa' => $this->empresa])
            ->set('Q17', 'nao');

        // A aba B é uma cópia do estado antes de A concluir: o rascunho (e seu id) é o mesmo.
        $abaA->call('submeter')->assertHasNoErrors();
        $diagnostico = Diagnostico::query()->sole();
        QuizRascunho::withTrashed()->restore();

        $abaB->call('proximoBloco')
            ->set('Q08', '50000')->set('Q09', '100000')->set('Q14', '20000')->set('Q15', '8000')->set('Q16', '2000')
            ->set('Q10', '90')->set('Q11', '15')->set('Q12', '15')->set('Q13', '2')
            ->call('proximoBloco')
            ->set('Q02', '50000')->set('Q03', '20000')->set('Q04', '10000')
            ->set('Q05', '300000')->set('Q06', '40000')->set('Q07', '80000')
            ->call('proximoBloco')->set('Q17', 'nao');

        // Faz a aba B usar o mesmo id de rascunho que a A usou.
        QuizRascunho::query()->update(['id' => ConsumoAnalise::query()->sole()->quiz_id]);

        $abaB->call('submeter')->assertNoRedirect()
            ->assertSee('Esta análise já foi concluída.')
            ->assertSeeHtml(route('diagnosticos.show', $diagnostico));

        expect($assinatura->fresh()->cota_restante_ciclo)->toBe(4)->and(Diagnostico::query()->count())->toBe(1);
    });
});

describe('erro ao consultar a carteira', function () {
    it('não bloqueia nem libera: pede para tentar de novo, e libera quando a consulta volta', function () {
        _ligarCobrancaQuiz();
        app(Carteira::class)->concederCota($this->usuario->id, 1, now()->subDay(), now()->addMonth());

        // A consulta ao saldo falha enquanto `$banco_fora` for verdadeiro.
        $banco_fora = true;
        DB::beforeExecuting(function (string $sql) use (&$banco_fora): void {
            if ($banco_fora && str_contains($sql, 'from "assinaturas"')) {
                throw new RuntimeException('banco fora');
            }
        });

        $tela = Livewire::test(Quiz::class, ['empresa' => $this->empresa])
            ->assertSet('erro_carteira', true)->assertSet('bloqueio_saldo', null)
            ->assertSee('Não conseguimos conferir seu saldo agora. Tente de novo em instantes.')
            ->assertDontSee('Bloco 1 de 4');

        $banco_fora = false;

        $tela->call('conferirSaldo')->assertSet('erro_carteira', false)->assertSee('Bloco 1 de 4');
    });
});

describe('CA-9 diagnósticos emitidos continuam acessíveis', function () {
    it('com a cobrança ligada e sem saldo, o relatório e o PDF abrem', function () {
        $diagnostico = Diagnostico::factory()->create(['usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $this->empresa->id]);
        _ligarCobrancaQuiz();

        expect(app(Carteira::class)->verificar($this->usuario->id)->permitido())->toBeFalse();

        $this->get(route('diagnosticos.show', $diagnostico))->assertOk();
        $this->get(route('diagnosticos.pdf', $diagnostico))->assertOk();
        $this->get(route('home'))->assertOk();
    });
});

describe('CA-11 texto de cada causa', function () {
    it('traz título e frase de cada causa da spec', function (Bloqueio $bloqueio, string $titulo, string $frase, array $acoes) {
        $texto = TextoBloqueio::para($bloqueio);

        expect($texto['titulo'])->toBe($titulo)
            ->and($texto['causa'])->toBe($frase)
            ->and(array_column($texto['acoes'], 'rotulo'))->toBe($acoes);
    })->with(function () {
        $d = CarbonImmutable::parse('2026-12-01 15:00:00');

        return [
            'sem nada, trial elegível' => [new Bloqueio(MotivoBloqueio::SemNada, trialElegivel: true), 'Você ainda não tem análises', 'Para fazer uma análise, comece o teste grátis ou escolha um plano.', ['Começar teste grátis', 'Ver planos']],
            'sem nada, trial indisponível' => [new Bloqueio(MotivoBloqueio::SemNada), 'Você ainda não tem análises', 'Escolha um plano ou compre créditos para fazer uma análise.', ['Ver planos', 'Comprar créditos']],
            'teste usado' => [new Bloqueio(MotivoBloqueio::TesteUsado, $d), 'Você usou a análise do teste', 'O teste termina em 01/12/2026. Assine ou compre créditos para fazer outra análise.', ['Ver planos', 'Comprar créditos']],
            'teste encerrado' => [new Bloqueio(MotivoBloqueio::TesteEncerrado, $d), 'Seu teste terminou', 'Seu teste terminou em 01/12/2026. Seus diagnósticos continuam disponíveis.', ['Ver planos', 'Comprar créditos']],
            'cota esgotada' => [new Bloqueio(MotivoBloqueio::CotaEsgotada, $d, 10), 'Você usou as análises deste ciclo', 'Suas 10 análises do ciclo acabaram. Novas análises em 01/12/2026.', ['Comprar créditos', 'Trocar de plano']],
            'cota esgotada, plano cancelado' => [new Bloqueio(MotivoBloqueio::CotaEsgotada, $d, 10, renova: false), 'Você usou as análises deste ciclo', 'Suas 10 análises do ciclo acabaram. Seu plano termina em 01/12/2026.', ['Comprar créditos', 'Trocar de plano']],
            'créditos vencidos' => [new Bloqueio(MotivoBloqueio::CreditosVencidos, $d), 'Seus créditos venceram', 'Os créditos venceram em 01/12/2026 e não podem mais ser usados.', ['Comprar créditos', 'Ver planos']],
            'em atraso' => [new Bloqueio(MotivoBloqueio::EmAtraso, $d), 'Sua assinatura está em atraso', 'Não conseguimos cobrar a renovação. Atualize o cartão para voltar a fazer análises.', ['Atualizar cartão', 'Ver meu plano']],
            'plano encerrado' => [new Bloqueio(MotivoBloqueio::PlanoEncerrado, $d), 'Seu plano terminou', 'Seu plano terminou em 01/12/2026. Seus diagnósticos continuam disponíveis.', ['Ver planos', 'Comprar créditos']],
            'desconto acabou' => [new Bloqueio(MotivoBloqueio::DescontoAcabouSemCartao, $d), 'Falta cadastrar um cartão', 'O desconto terminou em 01/12/2026. Cadastre um cartão para continuar.', ['Cadastrar cartão', 'Ver meu plano']],
        ];
    });

    it('mostra a data no fuso de Brasília', function () {
        $texto = TextoBloqueio::para(new Bloqueio(MotivoBloqueio::PlanoEncerrado, CarbonImmutable::parse('2026-12-01 01:00:00', 'UTC')));

        expect($texto['causa'])->toContain('30/11/2026');
    });

    it('esconde as ações enquanto as telas não existem e as mostra quando a rota nasce', function () {
        $acoes = TextoBloqueio::para(new Bloqueio(MotivoBloqueio::SemNada))['acoes'];

        expect(TextoBloqueio::disponiveis($acoes))->toBe([]);

        Route::get('/planos-teste', fn () => 'planos')->name('planos');
        Route::getRoutes()->refreshNameLookups();

        expect(TextoBloqueio::disponiveis($acoes))->toBe([['chave' => 'planos', 'rotulo' => 'Ver planos', 'href' => url('/planos-teste')]]);
    });

    it('com a rota existente a ação aparece no bloqueio, primária primeiro', function () {
        _ligarCobrancaQuiz();
        DB::table('trial_config')->update(['ativo' => false]);
        Route::get('/planos-teste', fn () => 'planos')->name('planos');
        Route::get('/creditos-teste', fn () => 'creditos')->name('creditos.comprar');
        Route::getRoutes()->refreshNameLookups();

        $html = Livewire::test(Quiz::class, ['empresa' => $this->empresa])->html();

        expect($html)->toContain('dusk="screen-bloqueio-acao-primaria"')->and($html)->toContain('dusk="screen-bloqueio-acao-secundaria"')
            ->and(strpos($html, 'Ver planos'))->toBeLessThan(strpos($html, 'Comprar créditos'));
    });

    it('sem nenhuma rota, o bloqueio não mostra botões de ação', function () {
        _ligarCobrancaQuiz();

        $html = Livewire::test(Quiz::class, ['empresa' => $this->empresa])->html();

        expect($html)->not->toContain('screen-bloqueio-acao-primaria')->and($html)->not->toContain('screen-bloqueio-acao-secundaria');
    });
});
