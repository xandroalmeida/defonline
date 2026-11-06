<?php

declare(strict_types=1);

use App\Livewire\Senha\RedefinirSenha;
use App\Livewire\Senha\SolicitarRedefinicao;
use App\Mail\RedefinicaoSenhaMessage;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/** STORY-100 — recuperação de senha pelo e-mail. */
beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('senha-esqueci');
});

function _contaComSenha(): Usuario
{
    return Usuario::factory()->create(['senha_hash' => 'SenhaAntiga1', 'email_confirmed_at' => now()]);
}

function _tokenDe(Usuario $u): string
{
    return Password::broker()->createToken($u);
}

it('CA-1 — o login leva à recuperação, e a tela abre sem conta', function () {
    $this->get(route('login'))->assertOk()->assertSee(route('senha.esqueci'), false);
    $this->get(route('senha.esqueci'))->assertOk()->assertSee('Esqueci minha senha');
});

it('CA-2 — envia o e-mail com o link, sem o e-mail na URL', function () {
    $u = _contaComSenha();

    Livewire::test(SolicitarRedefinicao::class)->set('email', $u->email)->call('enviar')
        ->assertHasNoErrors()
        ->assertSet('enviado', true);

    Mail::assertQueued(RedefinicaoSenhaMessage::class, fn ($m) => $m->hasTo($u->email)
        && str_contains($m->link, "/senha/redefinir/{$u->id}/")
        && ! str_contains($m->link, urlencode($u->email))
        && $m->minutos === 30);
});

it('CA-3 — e-mail sem conta tem a mesma resposta e não envia nada', function () {
    Livewire::test(SolicitarRedefinicao::class)->set('email', 'ninguem@example.com')->call('enviar')
        ->assertHasNoErrors()
        ->assertSet('enviado', true);

    Mail::assertNothingQueued();
});

it('CA-3 — conta excluída não recebe', function () {
    $u = _contaComSenha();
    $email = $u->email;
    $u->delete();

    Livewire::test(SolicitarRedefinicao::class)->set('email', $email)->call('enviar')->assertSet('enviado', true);

    Mail::assertNothingQueued();
});

it('recusa e-mail mal escrito', function () {
    Livewire::test(SolicitarRedefinicao::class)->set('email', 'nao-e-email')->call('enviar')
        ->assertHasErrors('email')
        ->assertSet('enviado', false);
});

it('CA-4 — pedidos em excesso não enviam mais e-mails, com a mesma resposta', function () {
    $u = _contaComSenha();
    config(['auth.passwords.users.throttle' => 0]);

    foreach (range(1, 7) as $_) {
        Livewire::test(SolicitarRedefinicao::class)->set('email', $u->email)->call('enviar')->assertSet('enviado', true);
    }

    Mail::assertQueuedCount(5);
});

it('CA-5 — troca a senha, encerra as sessões, invalida o link e volta ao login', function () {
    config(['session.driver' => 'database']);
    $u = _contaComSenha();
    $outro = _contaComSenha();
    $sessao = fn (string $id, string $dono) => DB::table('sessions')->insert(['id' => $id, 'user_id' => $dono, 'payload' => '', 'last_activity' => time()]);
    $sessao('sessao-do-usuario', $u->id);
    $sessao('sessao-de-outro', $outro->id);
    $token = _tokenDe($u);

    Livewire::test(RedefinirSenha::class, ['usuario' => $u->id, 'token' => $token])
        ->set('novaSenha', 'SenhaNova456')
        ->set('novaSenha_confirmation', 'SenhaNova456')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('SenhaNova456', $u->fresh()->senha_hash))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'sessao-do-usuario')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'sessao-de-outro')->exists())->toBeTrue();

    Livewire::test(RedefinirSenha::class, ['usuario' => $u->id, 'token' => $token])->assertSet('linkInvalido', true);
});

it('CA-6 — o link vence em 30 minutos', function () {
    $u = _contaComSenha();
    $token = _tokenDe($u);

    $this->travel(29)->minutes();
    Livewire::test(RedefinirSenha::class, ['usuario' => $u->id, 'token' => $token])->assertSet('linkInvalido', false);

    $this->travel(2)->minutes();
    Livewire::test(RedefinirSenha::class, ['usuario' => $u->id, 'token' => $token])
        ->assertSet('linkInvalido', true)
        ->assertSee('Link inválido ou vencido');
});

it('CA-6 — token errado ou de outra conta não troca a senha', function () {
    $u = _contaComSenha();
    $outro = _contaComSenha();

    Livewire::test(RedefinirSenha::class, ['usuario' => $u->id, 'token' => 'inventado'])->assertSet('linkInvalido', true);
    Livewire::test(RedefinirSenha::class, ['usuario' => $outro->id, 'token' => _tokenDe($u)])->assertSet('linkInvalido', true);

    $tela = Livewire::test(RedefinirSenha::class, ['usuario' => $u->id, 'token' => 'inventado'])
        ->set('novaSenha', 'SenhaNova456')
        ->set('novaSenha_confirmation', 'SenhaNova456')
        ->call('salvar');

    $tela->assertSet('linkInvalido', true);
    expect(Hash::check('SenhaAntiga1', $u->fresh()->senha_hash))->toBeTrue();
});

it('CA-7 — a nova senha segue as regras do cadastro', function (string $nova, string $confirmacao) {
    $u = _contaComSenha();

    Livewire::test(RedefinirSenha::class, ['usuario' => $u->id, 'token' => _tokenDe($u)])
        ->set('novaSenha', $nova)
        ->set('novaSenha_confirmation', $confirmacao)
        ->call('salvar')
        ->assertHasErrors('novaSenha');

    expect(Hash::check('SenhaAntiga1', $u->fresh()->senha_hash))->toBeTrue();
})->with([
    'curta' => ['a1', 'a1'],
    'sem número' => ['somenteletras', 'somenteletras'],
    'confirmação diferente' => ['SenhaNova456', 'SenhaNova789'],
]);

it('o e-mail tem HTML, texto, o link e o prazo', function () {
    $m = new RedefinicaoSenhaMessage('Ana', 'https://app/senha/abc', 30);

    $m->assertHasSubject('Redefina sua senha — DEFOnline');
    $m->assertSeeInHtml('https://app/senha/abc');
    $m->assertSeeInHtml('30 minutos');
    $m->assertSeeInText('https://app/senha/abc');
});
