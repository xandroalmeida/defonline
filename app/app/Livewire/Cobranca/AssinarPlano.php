<?php

declare(strict_types=1);

namespace App\Livewire\Cobranca;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\RenovacaoPix;
use App\Domain\Cobranca\VitrinePrecos;
use App\Jobs\EnviarEmailConfirmacao;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Plano;
use App\Models\Usuario;
use App\Services\Cobranca\CompraNaoPermitida;
use App\Services\Cobranca\IniciarAssinatura;
use App\Support\Cobranca\RotasCobranca;
use App\Support\Personificacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * STORY-077 CA-1, CA-7, CA-9, CA-11 — revisão do plano e entrada no checkout recorrente (spec
 * STORY-070-checkout-assinatura, ajustada pelo ADR-010: o cartão é digitado na página do provedor,
 * então a tela não tem campos de cartão). Com a cobrança desligada, a rota é inexistente (404).
 */
#[Layout('components.layouts.app')]
final class AssinarPlano extends Component
{
    public string $planoId;

    public string $ciclo;

    public bool $aceito = false;

    /** `cartao` ou `pix`; o Pix só existe no ciclo anual (STORY-080). */
    #[Url(as: 'meio')]
    public string $meio = 'cartao';

    public bool $falhaDoProvedor = false;

    public bool $confirmacaoReenviada = false;

    public function mount(string $plano, string $ciclo): void
    {
        abort_unless(CobrancaConfig::ligada(), 404);

        $this->planoId = $plano;
        $this->ciclo = (CicloCobranca::tryFrom($ciclo) ?? CicloCobranca::Mensal)->value;
        $this->updatedMeio();
    }

    /** Meio inválido, ou Pix fora do anual, volta ao cartão. Trocar o meio não zera o aceite. */
    public function updatedMeio(): void
    {
        $meio = MeioPagamento::tryFrom($this->meio);

        if ($meio === null || ($meio === MeioPagamento::Pix && $this->ciclo !== CicloCobranca::Anual->value)) {
            $this->meio = MeioPagamento::Cartao->value;
        }

        $this->falhaDoProvedor = false;
    }

    public function updatedCiclo(): void
    {
        if (CicloCobranca::tryFrom($this->ciclo) === null) {
            $this->ciclo = CicloCobranca::Mensal->value;
        }

        $this->updatedMeio();
    }

    public function assinar(IniciarAssinatura $iniciar): mixed
    {
        abort_unless(CobrancaConfig::ligada(), 404);
        $this->falhaDoProvedor = false;

        if (! $this->aceito) {
            $this->addError('aceito', 'Marque o aceite do Termo para continuar.');

            return null;
        }

        $usuario = Auth::user();
        $plano = $this->plano();

        if (! $usuario instanceof Usuario || $plano === null) {
            return null;
        }

        try {
            $invoice = $iniciar->executar($usuario, $plano, CicloCobranca::from($this->ciclo), request()->ip(), substr((string) request()->userAgent(), 0, 255), MeioPagamento::from($this->meio));
        } catch (CompraNaoPermitida) {
            // A própria tela já mostra o motivo (e-mail, admin, plano, já assinante): só reavalia o estado.
            return null;
        } catch (ProvedorIndisponivel) {
            $this->falhaDoProvedor = true;

            return null;
        }

        return $this->redirect((string) $invoice->checkout_url);
    }

    public function reenviarConfirmacao(): void
    {
        $usuario = Auth::user();

        if (! $usuario instanceof Usuario || $usuario->emailConfirmado()) {
            return;
        }

        $chave = 'checkout-confirmacao:'.$usuario->id;

        if (! RateLimiter::tooManyAttempts($chave, 3)) {
            RateLimiter::hit($chave, 3600);
            EnviarEmailConfirmacao::dispatch($usuario->id);
        }

        $this->confirmacaoReenviada = true;
    }

    public function render(): View
    {
        $usuario = Auth::user();
        $plano = $this->plano();
        $ciclo = CicloCobranca::from($this->ciclo);
        $vigente = $usuario instanceof Usuario ? IniciarAssinatura::assinaturaVigente($usuario->id) : null;

        return view('livewire.cobranca.assinar-plano', [
            'plano' => $plano,
            'cicloAtual' => $ciclo,
            'preco' => $plano === null ? 0 : IniciarAssinatura::precoDe($plano, $ciclo),
            'economia' => $plano === null ? 0 : VitrinePrecos::economiaPercentual($plano->preco_mensal_centavos, $plano->preco_anual_centavos),
            'proximaCobranca' => $ciclo->vencimento(now()),
            'meioAtual' => MeioPagamento::from($this->meio),
            'minutosPix' => (int) config('services.payments.pix_expiracao_minutos', 30),
            'avisoDias' => RenovacaoPix::AVISO_DIAS,
            'urlRenovar' => $vigente?->pagoComPix() === true && RenovacaoPix::janelaAberta($vigente->proximo_vencimento, now()) ? RotasCobranca::url(RotasCobranca::RENOVAR) : null,
            'vigente' => $vigente,
            'planoVigente' => $vigente === null ? null : Plano::query()->find($vigente->plano_id),
            'emTeste' => $usuario instanceof Usuario && $this->emTeste($usuario),
            'emailConfirmado' => $usuario instanceof Usuario && $usuario->emailConfirmado(),
            'email' => $usuario instanceof Usuario ? $usuario->email : '',
            'personificando' => Personificacao::ativa() !== null,
            'urlMeuPlano' => Route::has('meu-plano') ? route('meu-plano') : null,
            'urlTrocar' => $plano === null ? null : RotasCobranca::url(RotasCobranca::TROCAR, ['plano' => $plano->id, 'ciclo' => $this->ciclo]),
        ])->layoutData(['title' => 'Assinar plano']);
    }

    private function plano(): ?Plano
    {
        return Plano::query()->where('ativo', true)->find($this->planoId);
    }

    private function emTeste(Usuario $usuario): bool
    {
        return Assinatura::query()->where('usuario_id', $usuario->id)->where('trial', true)->whereIn('status', ['ativa', 'em_atraso'])->exists();
    }
}
