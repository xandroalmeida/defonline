<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Livewire\Conta\MinhaConta;
use App\Models\CobrancaConfig;
use App\Models\Cupom;
use App\Models\CupomConcessao;
use App\Models\CupomUso;
use App\Models\Invoice;
use App\Models\Usuario;
use App\Support\DataExibicao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/**
 * STORY-097 — Minha conta: editar nome, telefone e senha, e ver o resumo de plano, créditos, cupons e cobranças.
 */
function _usuarioDaConta(array $dados = []): Usuario
{
    return Usuario::factory()->create([
        'nome' => 'Maria da Silva',
        'telefone' => '11987654321',
        'senha_hash' => 'SenhaAtual123',
        'email_confirmed_at' => now(),
        ...$dados,
    ]);
}

function _cupomDaConta(string $codigo, array $dados = []): Cupom
{
    return Cupom::query()->create([
        'codigo' => $codigo,
        'tipo' => 'percentual',
        'valor' => 205,
        'vigencia_inicio' => now()->subDay(),
        'vigencia_fim' => now()->addMonth(),
        'ativo' => true,
        ...$dados,
    ]);
}

describe('acesso e navegação', function () {
    it('visitante é levado ao login', function () {
        $this->get('/conta')->assertRedirect(route('login'));
    });

    it('o menu e o dropdown levam à conta', function () {
        $html = $this->actingAs(_usuarioDaConta())->get(route('conta'))->assertOk()->getContent();
        $url = preg_quote(route('conta'), '/');

        expect($html)->toMatch('/<a(?=[^>]*href="'.$url.'")(?=[^>]*dusk="app-nav-conta")[^>]*>/s')
            ->and($html)->toMatch('/<a(?=[^>]*href="'.$url.'")(?=[^>]*dusk="app-header-conta-editar")[^>]*>/s')
            ->and($html)->toContain('<title>Minha conta · DEFOnline</title>');
    });
});

describe('dados pessoais', function () {
    it('traz os dados preenchidos, com o telefone formatado e o CPF mascarado', function () {
        $u = _usuarioDaConta();

        Livewire::actingAs($u)->test(MinhaConta::class)
            ->assertSet('nome', 'Maria da Silva')
            ->assertSet('telefone', '(11) 98765-4321')
            ->assertSee($u->email)
            ->assertSee($u->cpfMascarado())
            ->assertDontSee((string) $u->cpf);
    });

    it('salva nome e telefone normalizados', function () {
        $u = _usuarioDaConta();

        Livewire::actingAs($u)->test(MinhaConta::class)
            ->set('nome', '  Maria Souza  ')
            ->set('telefone', '(21) 3333-4444')
            ->call('salvarDados')
            ->assertHasNoErrors()
            ->assertRedirect(route('conta'));

        $u->refresh();
        expect($u->nome)->toBe('Maria Souza')->and($u->telefone)->toBe('2133334444');
    });

    it('recusa nome curto e telefone inválido sem alterar nada', function (string $campo, string $valor) {
        $u = _usuarioDaConta();

        Livewire::actingAs($u)->test(MinhaConta::class)
            ->set($campo, $valor)
            ->call('salvarDados')
            ->assertHasErrors($campo);

        $u->refresh();
        expect($u->nome)->toBe('Maria da Silva')->and($u->telefone)->toBe('11987654321');
    })->with([
        'nome curto' => ['nome', 'M'],
        'nome em branco' => ['nome', '   '],
        'telefone sem DDD' => ['telefone', '987654321'],
        'telefone com DDD inválido' => ['telefone', '(01) 98765-4321'],
    ]);

    it('salvar os dados não muda e-mail nem CPF', function () {
        $u = _usuarioDaConta();
        $email = $u->email;
        $cpf = $u->cpf;

        Livewire::actingAs($u)->test(MinhaConta::class)->set('nome', 'Maria Souza')->call('salvarDados');

        $u->refresh();
        expect($u->email)->toBe($email)->and($u->cpf)->toBe($cpf);
    });
});

describe('senha', function () {
    it('troca a senha e encerra as outras sessões', function () {
        config(['session.driver' => 'database']);
        $u = _usuarioDaConta();
        $outra = _usuarioDaConta();
        $sessao = fn (string $id, string $dono) => DB::table('sessions')->insert(['id' => $id, 'user_id' => $dono, 'payload' => '', 'last_activity' => time()]);
        $sessao('sessao-antiga', $u->id);
        $sessao('sessao-de-outra-pessoa', $outra->id);

        Livewire::actingAs($u)->test(MinhaConta::class)
            ->set('senhaAtual', 'SenhaAtual123')
            ->set('novaSenha', 'SenhaNova456')
            ->set('novaSenha_confirmation', 'SenhaNova456')
            ->call('trocarSenha')
            ->assertHasNoErrors()
            ->assertSet('senhaAtual', '')
            ->assertSet('novaSenha', '')
            ->assertSet('novaSenha_confirmation', '')
            ->assertSee('Senha alterada.');

        expect(Hash::check('SenhaNova456', $u->fresh()->senha_hash))->toBeTrue()
            ->and(DB::table('sessions')->where('id', 'sessao-antiga')->exists())->toBeFalse()
            ->and(DB::table('sessions')->where('id', 'sessao-de-outra-pessoa')->exists())->toBeTrue();
    });

    it('recusa senha atual errada e nova senha inválida', function (string $atual, string $nova, string $confirmacao, string $campo) {
        $u = _usuarioDaConta();

        Livewire::actingAs($u)->test(MinhaConta::class)
            ->set('senhaAtual', $atual)
            ->set('novaSenha', $nova)
            ->set('novaSenha_confirmation', $confirmacao)
            ->call('trocarSenha')
            ->assertHasErrors($campo);

        expect(Hash::check('SenhaAtual123', $u->fresh()->senha_hash))->toBeTrue();
    })->with([
        'atual errada' => ['Errada999', 'SenhaNova456', 'SenhaNova456', 'senhaAtual'],
        'curta' => ['SenhaAtual123', 'a1', 'a1', 'novaSenha'],
        'sem número' => ['SenhaAtual123', 'somenteletras', 'somenteletras', 'novaSenha'],
        'confirmação diferente' => ['SenhaAtual123', 'SenhaNova456', 'SenhaNova789', 'novaSenha'],
        'igual à atual' => ['SenhaAtual123', 'SenhaAtual123', 'SenhaAtual123', 'novaSenha'],
    ]);

    it('depois de 5 erros, nem a senha certa passa até o limite acabar', function () {
        $u = _usuarioDaConta();
        RateLimiter::clear('conta-senha:'.$u->id);
        $tela = Livewire::actingAs($u)->test(MinhaConta::class)
            ->set('novaSenha', 'SenhaNova456')
            ->set('novaSenha_confirmation', 'SenhaNova456');

        foreach (range(1, 5) as $_) {
            $tela->set('senhaAtual', 'Errada999')->call('trocarSenha');
        }

        $tela->set('senhaAtual', 'SenhaAtual123')->call('trocarSenha')->assertHasErrors('senhaAtual');

        expect(Hash::check('SenhaAtual123', $u->fresh()->senha_hash))->toBeTrue();
    });
});

describe('Logar como', function () {
    it('o administrador vê a conta só para leitura, com os campos desligados e sem os botões', function () {
        $u = _usuarioDaConta();

        $this->withSession(_sessaoDePersonificacao(_adminDaEquipe(), $u))->actingAs($u)
            ->get(route('conta'))->assertOk()
            ->assertSee('Aqui é só leitura.')
            ->assertSee('disabled', false)
            ->assertDontSee('screen-conta-salvar-dados', false)
            ->assertDontSee('screen-conta-trocar-senha', false);
    });

    it('as ações recusam no servidor, mesmo chamadas à força', function () {
        $u = _usuarioDaConta();
        _personificar(_adminDaEquipe(), $u);

        $tela = new MinhaConta;
        $tela->nome = 'Outro Nome';
        $tela->telefone = '21988887777';
        $tela->salvarDados();
        $tela->senhaAtual = 'SenhaAtual123';
        $tela->novaSenha = 'SenhaNova456';
        $tela->novaSenha_confirmation = 'SenhaNova456';
        $tela->trocarSenha();

        $u->refresh();
        expect($u->nome)->toBe('Maria da Silva')
            ->and($u->telefone)->toBe('11987654321')
            ->and(Hash::check('SenhaAtual123', $u->senha_hash))->toBeTrue();
    });
});

describe('resumo de plano, créditos, cupons e cobranças', function () {
    it('com a cobrança desligada, a tela funciona sem o resumo', function () {
        CobrancaConfig::atual()->update(['ativa' => false]);

        Livewire::actingAs(_usuarioDaConta())->test(MinhaConta::class)
            ->assertSee('Dados pessoais')
            ->assertDontSeeHtml('dusk="screen-conta-plano"')
            ->assertDontSeeHtml('dusk="screen-conta-cupons"');
    });

    it('sem plano, sem créditos e sem cupons, diz isso em cada bloco', function () {
        _usarProvedor('fake');

        Livewire::actingAs(_compradorConfirmado())->test(MinhaConta::class)
            ->assertSee('Você ainda não tem um plano.')
            ->assertSee('Nenhum crédito disponível.')
            ->assertSee('Nenhum cupom na sua conta.')
            ->assertSee('Você ainda não fez nenhuma compra.');
    });

    it('mostra o plano ativo, a cota e o vencimento', function () {
        _usarProvedor('fake');
        $u = _compradorConfirmado();
        [$a] = _assinaturaAtivaNoFake($u);

        Livewire::actingAs($u)->test(MinhaConta::class)
            ->assertSee('Básico')
            ->assertSee('Ativo')
            ->assertSee($a->cota_restante_ciclo.' de '.$a->cota_snapshot)
            ->assertSee(DataExibicao::data($a->proximo_vencimento))
            ->assertSeeHtml('href="'.route('meu-plano').'"');
    });

    it('soma só os créditos utilizáveis e mostra o vencimento mais próximo', function () {
        _usarProvedor('fake');
        $u = _compradorConfirmado();
        $carteira = app(Carteira::class);
        $carteira->concederCreditos($u->id, 2, now()->addMonths(6));
        $carteira->concederCreditos($u->id, 3, now()->addMonth());
        $vencido = $carteira->concederCreditos($u->id, 7, now()->addDay());
        $vencido->forceFill(['data_expiracao' => now()->subDay()])->save();

        Livewire::actingAs($u)->test(MinhaConta::class)
            ->assertSeeHtml('dusk="screen-conta-creditos-total">5<')
            ->assertSee('3 vencem em '.DataExibicao::data(now()->addMonth()));
    });

    it('não mostra crédito de outra pessoa', function () {
        _usarProvedor('fake');
        app(Carteira::class)->concederCreditos(_usuarioDaConta()->id, 9, now()->addMonth());

        Livewire::actingAs(_compradorConfirmado())->test(MinhaConta::class)
            ->assertSee('Nenhum crédito disponível.');
    });

    it('separa cupons disponíveis dos já usados e esconde os que não valem mais', function () {
        _usarProvedor('fake');
        $u = _compradorConfirmado();
        $conceder = fn (Cupom $c) => CupomConcessao::query()->create(['cupom_id' => $c->id, 'usuario_id' => $u->id, 'concedida_em' => now()]);

        $conceder(_cupomDaConta('VALE205'));
        $conceder(_cupomDaConta('FIXO10', ['tipo' => 'valor_fixo', 'valor' => 1000]));
        $conceder(_cupomDaConta('INATIVO', ['ativo' => false]));
        $conceder(_cupomDaConta('VENCIDO', ['vigencia_inicio' => now()->subMonth(), 'vigencia_fim' => now()->subDay()]));
        $usado = _cupomDaConta('JAUSADO');
        $conceder($usado);
        CupomUso::query()->create(['cupom_id' => $usado->id, 'usuario_id' => $u->id, 'desconto_centavos' => 1250, 'usado_em' => now()]);

        Livewire::actingAs($u)->test(MinhaConta::class)
            ->assertSeeInOrder(['Disponíveis', 'VALE205', '20,5% de desconto', 'Já usados', 'JAUSADO', 'R$ 12,50'])
            ->assertSee('FIXO10')
            ->assertSee('R$ 10,00 de desconto')
            ->assertDontSee('INATIVO')
            ->assertDontSee('VENCIDO');
    });

    it('não mostra cupom de outra pessoa', function () {
        _usarProvedor('fake');
        $c = _cupomDaConta('DEOUTRO');
        CupomConcessao::query()->create(['cupom_id' => $c->id, 'usuario_id' => _usuarioDaConta()->id, 'concedida_em' => now()]);

        Livewire::actingAs(_compradorConfirmado())->test(MinhaConta::class)->assertDontSee('DEOUTRO');
    });

    it('mostra só as 3 cobranças mais recentes', function () {
        _usarProvedor('fake');
        $u = _compradorConfirmado();
        _assinaturaAtivaNoFake($u);
        $base = Invoice::query()->where('usuario_id', $u->id)->firstOrFail();

        foreach (['Antiga', 'Dois', 'Tres', 'Quatro'] as $i => $nome) {
            $nova = $base->replicate();
            $nova->forceFill(['descricao' => "Cobranca {$nome}", 'id_externo' => "ext-{$i}", 'checkout_id_externo' => "chk-{$i}"]);
            $nova->created_at = now()->addMinutes($i + 1);
            $nova->save();
        }

        Livewire::actingAs($u)->test(MinhaConta::class)
            ->assertSeeInOrder(['Cobranca Quatro', 'Cobranca Tres', 'Cobranca Dois'])
            ->assertDontSee('Cobranca Antiga');
    });
});
