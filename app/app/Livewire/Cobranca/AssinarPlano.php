<?php

declare(strict_types=1);

namespace App\Livewire\Cobranca;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\DescontoDaAssinatura;
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
use App\Services\Cobranca\CupomAplicado;
use App\Services\Cobranca\CupomRecusado;
use App\Services\Cobranca\IniciarAssinatura;
use App\Services\Cobranca\ItemDeCompra;
use App\Support\Cobranca\RotasCobranca;
use App\Support\DataExibicao;
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
    use ComCupom;

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
            $invoice = $iniciar->executar($usuario, $plano, CicloCobranca::from($this->ciclo), request()->ip(), substr((string) request()->userAgent(), 0, 255), MeioPagamento::from($this->meio), $this->codigoCupom);
        } catch (CupomRecusado $e) {
            $this->cupomRecusadoNoClique($e);

            return null;
        } catch (CompraNaoPermitida) {
            // A própria tela já mostra o motivo (e-mail, admin, plano, já assinante): só reavalia o estado.
            return null;
        } catch (ProvedorIndisponivel) {
            $this->falhaDoProvedor = true;

            return null;
        }

        // Sem cobrança (STORY-083 CA-6) não há página do provedor: vai direto à confirmação.
        return $this->redirect($invoice->checkout_url ?? route('assinatura.retorno', $invoice));
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
        $preco = $plano === null ? 0 : IniciarAssinatura::precoDe($plano, $ciclo);
        $aplicado = $plano === null || $vigente !== null ? null : $this->cupomDaTela();
        $final = $aplicado?->preco->final ?? $preco;

        return view('livewire.cobranca.assinar-plano', [
            'plano' => $plano,
            'cicloAtual' => $ciclo,
            'preco' => $preco,
            'aplicado' => $aplicado,
            'cupomConcedido' => $aplicado?->concedido === true,
            'aPagar' => $final,
            'semCobranca' => $aplicado?->preco->semCobranca() === true,
            'cupomResumo' => $aplicado === null ? null : $this->resumoDoCupom($aplicado, $ciclo),
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

    protected function itemDeCompra(): ?ItemDeCompra
    {
        $plano = $this->plano();
        $ciclo = CicloCobranca::tryFrom($this->ciclo);

        return $plano === null || $ciclo === null ? null : new ItemDeCompra(IniciarAssinatura::precoDe($plano, $ciclo), $plano->id, $ciclo->value);
    }

    /**
     * As linhas "Próxima cobrança" e "Depois" do resumo (spec STORY-070-cupom-no-checkout §3): o desconto tem fim e ele
     * está dito antes de pagar (CA-7).
     *
     * @return array{proxima: string, depois: string, ciclo: string}
     */
    private function resumoDoCupom(CupomAplicado $aplicado, CicloCobranca $ciclo): array
    {
        $reais = fn (int $c): string => 'R$ '.number_format($c / 100, 2, ',', '.');
        $d = new DescontoDaAssinatura($aplicado->preco->cheio, $aplicado->preco->desconto, $aplicado->ciclos);
        $fim = $d->fim($ciclo, now());
        $segundo = $d->valorDoPeriodo(2);
        $pix = $this->meio === MeioPagamento::Pix->value;
        $cheio = $reais($aplicado->preco->cheio).' '.$ciclo->porExtenso();

        if ($pix) {
            return [
                'ciclo' => 'Anual, não renova sozinho — avisamos '.RenovacaoPix::AVISO_DIAS.' dias antes',
                'proxima' => 'Não há cobrança automática. Renovação em '.DataExibicao::data($ciclo->vencimento(now())),
                'depois' => $fim === null ? 'Você paga '.$reais($segundo).' por um novo QR Code, se quiser continuar' : 'Você paga '.$reais($d->cheio).' por um novo QR Code, a partir de '.DataExibicao::data($fim).', se quiser continuar',
            ];
        }

        if ($aplicado->preco->semCobranca()) {
            return [
                'ciclo' => $ciclo->rotulo().'. Sem cobrança enquanto durar o desconto',
                'proxima' => $fim === null ? 'Nenhuma' : 'Nenhuma até '.DataExibicao::data($fim),
                'depois' => $fim === null ? $cheio : $cheio.', a partir de '.DataExibicao::data($fim).'. Cadastre um cartão em Meu plano para continuar.',
            ];
        }

        return [
            'ciclo' => $ciclo->rotulo().', renova sozinho no cartão',
            'proxima' => DataExibicao::data($ciclo->vencimento(now())).' · '.$reais($segundo),
            'depois' => $fim === null ? $reais($segundo).' '.$ciclo->porExtenso() : $cheio.', a partir de '.DataExibicao::data($fim),
        ];
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
