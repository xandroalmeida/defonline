<?php

declare(strict_types=1);

namespace App\Livewire\Trial;

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\ElegibilidadeTrial;
use App\Domain\Cobranca\MotivoInelegivelTrial;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\Trial;
use App\Domain\Cobranca\TrialIndisponivel;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\EmpresaAnalisada;
use App\Models\TrialConfig;
use App\Support\Personificacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * STORY-074 / spec STORY-070-trial — oferta, ativação em um clique (sem meio de pagamento),
 * confirmação, andamento e os motivos de recusa. A ativação é uma ação POST do Livewire: abrir a
 * página (que é GET, para servir de destino de link no bloqueio e em planos) nunca ativa nada.
 * Com a cobrança desligada a página responde 404 (STORY-073 CA-1).
 */
#[Layout('components.layouts.app')]
final class Ativar extends Component
{
    /** Preenchido ao ativar nesta requisição: id da assinatura recém-criada. */
    public ?string $ativadaId = null;

    public ?string $motivo = null;

    public bool $erroInesperado = false;

    public function mount(): void
    {
        abort_unless(CobrancaConfig::ligada(), 404);
    }

    public function ativar(): void
    {
        if (Personificacao::ativa() !== null) {
            return;
        }

        $this->motivo = null;
        $this->erroInesperado = false;

        try {
            $assinatura = app(Trial::class)->ativar((string) auth()->id());
            $this->ativadaId = $assinatura->id;
        } catch (TrialIndisponivel $e) {
            $this->motivo = $e->motivo->value;
        } catch (Throwable $e) {
            Log::error('trial.ativacao.falha', ['usuario_id' => auth()->id(), 'exception' => $e::class, 'message' => $e->getMessage()]);
            $this->erroInesperado = true;
        }
    }

    public function render(): View
    {
        $usuarioId = (string) auth()->id();
        $trialAtual = Assinatura::query()
            ->where('usuario_id', $usuarioId)->where('trial', true)->where('status', StatusAssinatura::Ativa->value)
            ->where('proximo_vencimento', '>', now())->first();

        $motivo = $this->motivo !== null ? MotivoInelegivelTrial::from($this->motivo) : null;
        $oferta = null;

        if ($this->ativadaId === null && $trialAtual === null && ! $this->erroInesperado) {
            $motivo ??= app(ElegibilidadeTrial::class)->motivo($usuarioId);
            $oferta = $motivo === null ? TrialConfig::query()->first() : null;
        }

        return view('livewire.trial.ativar', [
            'trial' => $this->ativadaId !== null ? Assinatura::query()->find($this->ativadaId) : $trialAtual,
            'recemAtivado' => $this->ativadaId !== null,
            'oferta' => $oferta,
            'inelegivel' => $motivo,
            'erroInesperado' => $this->erroInesperado,
            'personificando' => Personificacao::ativa() !== null,
            'temEmpresa' => EmpresaAnalisada::query()->exists(),
            'urlPlanos' => route('planos'),
            'aviso' => app(Carteira::class)->avisoDoTeste($usuarioId),
        ])->layoutData(['title' => 'Teste grátis']);
    }
}
