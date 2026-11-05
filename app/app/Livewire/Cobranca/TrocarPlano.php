<?php

declare(strict_types=1);

namespace App\Livewire\Cobranca;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\PreviaDeTroca;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\Plano;
use App\Models\Usuario;
use App\Services\Cobranca\TrocaNegada;
use App\Services\Cobranca\TrocarDePlano;
use App\Support\Personificacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-082 CA-1, CA-2, CA-4, CA-5, CA-6 — revisão da troca de plano (spec STORY-070-troca-de-plano).
 * Mostra o que muda e quanto se paga hoje ANTES de confirmar; upgrade abre o checkout da diferença e
 * downgrade só agenda. Com a cobrança desligada a rota é inexistente (404). No "Logar como" é só leitura.
 */
#[Layout('components.layouts.app')]
final class TrocarPlano extends Component
{
    public string $planoId;

    public string $ciclo;

    /** Resultado quando a troca valeu na hora (`aplicada`) ou foi agendada (`agendada`). */
    public ?string $resultado = null;

    public ?string $erro = null;

    public function mount(string $plano, string $ciclo): void
    {
        abort_unless(CobrancaConfig::ligada(), 404);

        $this->planoId = $plano;
        $this->ciclo = (CicloCobranca::tryFrom($ciclo) ?? CicloCobranca::Mensal)->value;
    }

    public function updatedCiclo(): void
    {
        if (CicloCobranca::tryFrom($this->ciclo) === null) {
            $this->ciclo = CicloCobranca::Mensal->value;
        }

        $this->resultado = null;
        $this->erro = null;
    }

    public function confirmar(TrocarDePlano $trocas): mixed
    {
        abort_unless(CobrancaConfig::ligada(), 404);
        $this->erro = null;

        $usuario = Auth::user();
        $destino = $this->destino();

        if (! $usuario instanceof Usuario || $destino === null) {
            return null;
        }

        try {
            $feito = $trocas->confirmar($usuario, $destino, CicloCobranca::from($this->ciclo));
        } catch (TrocaNegada) {
            // A própria tela já mostra o motivo: só reavalia.
            return null;
        } catch (ProvedorIndisponivel) {
            $this->erro = 'Não conseguimos concluir a troca agora. Seu plano continua o mesmo.';

            return null;
        }

        if ($feito->tipo === 'checkout') {
            return $this->redirect((string) $feito->invoice?->checkout_url);
        }

        $this->resultado = $feito->tipo;

        return null;
    }

    public function render(TrocarDePlano $trocas): View
    {
        $usuario = Auth::user();
        $destino = $this->destino();
        $ciclo = CicloCobranca::from($this->ciclo);
        $assinatura = null;
        $previa = null;
        $negada = null;

        if ($usuario instanceof Usuario) {
            try {
                $assinatura = $trocas->assinaturaDaTroca($usuario, paraLeitura: true);
                $previa = $destino === null ? null : $trocas->previa($assinatura, $destino, $ciclo);
            } catch (TrocaNegada $e) {
                $negada = $e->motivo;
            }
        }

        // Mudou de ideia em outra aba e a troca já valeu (ou foi agendada): a tela mostra o resultado, não a revisão.
        $atual = $assinatura === null ? null : Plano::query()->find($assinatura->plano_id);

        return view('livewire.cobranca.trocar-plano', [
            'destino' => $destino,
            'cicloNovo' => $ciclo,
            'assinatura' => $assinatura,
            'atual' => $atual,
            'previa' => $previa instanceof PreviaDeTroca ? $previa : null,
            'negada' => $negada,
            'personificando' => Personificacao::ativa() !== null,
            'agendada' => $assinatura instanceof Assinatura ? TrocarDePlano::agendada($assinatura) : null,
            'minutosPix' => (int) config('services.payments.pix_expiracao_minutos', 30),
        ])->layoutData(['title' => 'Trocar plano']);
    }

    private function destino(): ?Plano
    {
        return Plano::query()->find($this->planoId);
    }
}
