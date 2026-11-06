<?php

declare(strict_types=1);

namespace App\Livewire\Conta;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\CupomRegras;
use App\Domain\Cobranca\SituacaoDoPlano;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Cupom;
use App\Models\CupomConcessao;
use App\Models\CupomUso;
use App\Models\LoteCredito;
use App\Models\Plano;
use App\Models\Usuario;
use App\Support\Cobranca\HistoricoDeCobrancas;
use App\Support\Personificacao;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * STORY-097 — Minha conta: edita nome, telefone e senha, e resume plano, créditos, cupons e cobranças.
 * Só lê a cobrança; contratar, cancelar e trocar cartão continuam em Meu plano. E-mail e CPF não mudam aqui
 * (EPIC-004). No "Logar como" nada é alterado.
 */
#[Layout('components.layouts.app')]
final class MinhaConta extends Component
{
    private const TENTATIVAS_DE_SENHA = 5;

    private const BLOQUEIO_DE_SENHA_SEGUNDOS = 900;

    private const COBRANCAS_NO_RESUMO = 3;

    private const USOS_NO_RESUMO = 5;

    public string $nome = '';

    public string $telefone = '';

    public string $senhaAtual = '';

    public string $novaSenha = '';

    public string $novaSenha_confirmation = '';

    public bool $senhaAlterada = false;

    /** STORY-099 — e-mails para renovar o diagnóstico: é aqui que quem se descadastrou volta a receber. */
    public bool $receberAvisoRenovacao = true;

    public function mount(): void
    {
        $usuario = $this->usuario();
        $this->nome = $usuario->nome;
        $this->telefone = $this->telefoneFormatado($usuario->telefone);
        $this->receberAvisoRenovacao = $usuario->aviso_renovacao_descadastrado_em === null;
    }

    public function updatedReceberAvisoRenovacao(): void
    {
        $usuario = $this->usuario();

        if (Personificacao::ativa() !== null) {
            $this->receberAvisoRenovacao = $usuario->aviso_renovacao_descadastrado_em === null;

            return;
        }

        $usuario->forceFill(['aviso_renovacao_descadastrado_em' => $this->receberAvisoRenovacao ? null : now()])->save();
    }

    public function salvarDados(): mixed
    {
        if (Personificacao::ativa() !== null) {
            return null;
        }

        $validados = $this->validate(
            [
                'nome' => ['required', 'string', 'min:2', 'max:120'],
                'telefone' => ['required', 'string', 'regex:/^[1-9]{2}9?\d{8}$/'],
            ],
            [
                'nome.required' => 'Informe o nome completo.',
                'nome.min' => 'Informe o nome completo.',
                'nome.max' => 'O nome pode ter até 120 caracteres.',
                'telefone.required' => 'Informe o telefone WhatsApp.',
                'telefone.regex' => 'Informe DDD e número, como (11) 98888-7777.',
            ],
        );

        $this->usuario()->update(['nome' => $validados['nome'], 'telefone' => $validados['telefone']]);
        session()->flash('conta-dados-salvos', true);

        // O primeiro nome aparece no cabeçalho, que está fora deste componente.
        return $this->redirect(route('conta'));
    }

    public function trocarSenha(): void
    {
        $this->senhaAlterada = false;

        if (Personificacao::ativa() !== null) {
            return;
        }

        $usuario = $this->usuario();
        $chave = 'conta-senha:'.$usuario->id;

        if (RateLimiter::tooManyAttempts($chave, self::TENTATIVAS_DE_SENHA)) {
            $this->addError('senhaAtual', 'Muitas tentativas. Tente de novo em alguns minutos.');

            return;
        }

        $this->validate(
            [
                'senhaAtual' => ['required', 'string'],
                'novaSenha' => ['required', 'confirmed', 'different:senhaAtual', Password::min(8)->letters()->numbers()],
            ],
            [
                'senhaAtual.required' => 'Informe a senha atual.',
                'novaSenha.required' => 'Informe a nova senha.',
                'novaSenha.confirmed' => 'A confirmação não é igual à nova senha.',
                'novaSenha.different' => 'A nova senha precisa ser diferente da atual.',
                'novaSenha.min' => 'A nova senha precisa ter ao menos 8 caracteres.',
                'novaSenha.letters' => 'A nova senha precisa ter letras e números.',
                'novaSenha.numbers' => 'A nova senha precisa ter letras e números.',
            ],
        );

        if (! Hash::check($this->senhaAtual, $usuario->senha_hash)) {
            RateLimiter::hit($chave, self::BLOQUEIO_DE_SENHA_SEGUNDOS);
            $this->addError('senhaAtual', 'Senha atual incorreta.');

            return;
        }

        $usuario->update(['senha_hash' => $this->novaSenha]);
        RateLimiter::clear($chave);
        $this->encerrarOutrasSessoes($usuario);
        Log::info('conta.senha_alterada', ['module' => 'conta', 'action' => 'trocar_senha', 'user_id' => $usuario->id]);

        $this->reset('senhaAtual', 'novaSenha', 'novaSenha_confirmation');
        $this->senhaAlterada = true;
    }

    public function render(): View
    {
        $usuario = $this->usuario();
        $cobrancaLigada = CobrancaConfig::ligada();

        return view('livewire.conta.minha-conta', [
            'usuario' => $usuario,
            'personificando' => Personificacao::ativa() !== null,
            'cobrancaLigada' => $cobrancaLigada,
            ...($cobrancaLigada ? $this->resumoDaCobranca($usuario) : []),
        ])->layoutData([
            'title' => 'Minha conta',
            'breadcrumb' => [['label' => 'Início', 'url' => route('home')], ['label' => 'Minha conta']],
        ]);
    }

    /**
     * O nome e o telefone chegam como foram digitados; valem sem espaços nas pontas e só com os dígitos.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function prepareForValidation($attributes): array
    {
        $attributes['nome'] = trim($this->nome);
        $attributes['telefone'] = preg_replace('/\D+/', '', $this->telefone) ?? '';

        return $attributes;
    }

    /** @return array<string, mixed> */
    private function resumoDaCobranca(Usuario $usuario): array
    {
        $agora = CarbonImmutable::now();
        $assinatura = Assinatura::query()
            ->where('usuario_id', $usuario->id)
            ->orderByDesc('data_inicio')
            ->orderByDesc('created_at')
            ->first();
        $vencimento = $assinatura === null ? null : CarbonImmutable::instance($assinatura->proximo_vencimento);
        $lotes = LoteCredito::query()
            ->where('usuario_id', $usuario->id)
            ->disponiveis($agora)
            ->orderBy('data_expiracao')
            ->get();
        [$cobrancas, $cobrancasFalharam] = $this->cobrancas($usuario->id);

        return [
            'assinatura' => $assinatura,
            'situacao' => SituacaoDoPlano::de($assinatura?->status, $assinatura->trial ?? false, $vencimento, $agora),
            'vencimento' => $vencimento,
            'plano' => $assinatura?->plano_id === null ? null : Plano::query()->find($assinatura->plano_id),
            'ciclo' => $assinatura?->ciclo === null ? null : CicloCobranca::tryFrom($assinatura->ciclo),
            'creditos' => (int) $lotes->sum('quantidade_restante'),
            'proximoLote' => $lotes->first(),
            'cupomDoPlano' => $this->cupomDoPlano($assinatura),
            'cuponsDisponiveis' => $this->cuponsDisponiveis($usuario->id, $agora),
            'cuponsUsados' => $this->cuponsUsados($usuario->id),
            'cobrancas' => $cobrancas,
            'cobrancasFalharam' => $cobrancasFalharam,
        ];
    }

    private function cupomDoPlano(?Assinatura $assinatura): ?string
    {
        if ($assinatura === null || $assinatura->trial || $assinatura->cupom_id === null || $assinatura->desconto() === null) {
            return null;
        }

        return Cupom::query()->whereKey($assinatura->cupom_id)->value('codigo');
    }

    /**
     * Cupons concedidos à conta que ainda valem. O item comprado é conferido no checkout, que aplica o cupom sozinho.
     *
     * @return list<Cupom>
     */
    private function cuponsDisponiveis(string $usuarioId, CarbonImmutable $agora): array
    {
        $ids = CupomConcessao::query()->where('usuario_id', $usuarioId)->pluck('cupom_id');

        return Cupom::query()->whereIn('id', $ids)->orderBy('vigencia_fim')->get()
            ->filter(fn (Cupom $c): bool => CupomRegras::avaliarUso(
                $c->ativo,
                $c->vigencia_inicio,
                $c->vigencia_fim,
                $c->limite_usos,
                $c->usos()->count(),
                true,
                $c->usos()->where('usuario_id', $usuarioId)->exists(),
                $agora,
            ) === null)
            ->values()
            ->all();
    }

    /** @return list<array{codigo: string, desconto_centavos: int, usado_em: mixed}> */
    private function cuponsUsados(string $usuarioId): array
    {
        $usos = CupomUso::query()->where('usuario_id', $usuarioId)->orderByDesc('usado_em')->limit(self::USOS_NO_RESUMO)->get();
        $codigos = Cupom::query()->whereIn('id', $usos->pluck('cupom_id'))->pluck('codigo', 'id');

        return $usos
            ->map(fn (CupomUso $u): array => [
                'codigo' => (string) ($codigos[$u->cupom_id] ?? '—'),
                'desconto_centavos' => $u->desconto_centavos,
                'usado_em' => $u->usado_em,
            ])
            ->all();
    }

    /**
     * Como em Meu plano, a falha das cobranças não derruba o resto da tela.
     *
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private function cobrancas(string $usuarioId): array
    {
        try {
            return [array_slice(app(HistoricoDeCobrancas::class)->do($usuarioId), 0, self::COBRANCAS_NO_RESUMO), false];
        } catch (Throwable) {
            return [[], true];
        }
    }

    /** Quem troca a senha porque alguém a descobriu precisa derrubar as outras sessões. */
    private function encerrarOutrasSessoes(Usuario $usuario): void
    {
        session()->regenerate();

        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $usuario->id)
                ->where('id', '!=', session()->getId())
                ->delete();
        }
    }

    private function telefoneFormatado(string $digitos): string
    {
        return match (strlen($digitos)) {
            11 => '('.substr($digitos, 0, 2).') '.substr($digitos, 2, 5).'-'.substr($digitos, 7),
            10 => '('.substr($digitos, 0, 2).') '.substr($digitos, 2, 4).'-'.substr($digitos, 6),
            default => $digitos,
        };
    }

    private function usuario(): Usuario
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof Usuario, 403);

        return $usuario;
    }
}
