<?php

declare(strict_types=1);

namespace App\Livewire\Empresa;

use App\Models\EmpresaAnalisada;
use App\Observabilidade\AuditLogger;
use App\Services\Diagnostico\RenovacaoDeDiagnostico;
use App\Support\Personificacao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** STORY-099 CA-1 — o usuário escolhe, por empresa, depois de quanto tempo quer o aviso. */
final class PrazoRenovacaoDiagnostico extends Component
{
    #[Locked]
    public string $empresaId;

    public int $meses = 6;

    public bool $salvo = false;

    public function mount(EmpresaAnalisada $empresa): void
    {
        $this->empresaId = $empresa->id;
        $this->meses = $empresa->renovacao_diagnostico_meses;
    }

    public function updatedMeses(RenovacaoDeDiagnostico $renovacao): void
    {
        $this->salvo = false;
        $empresa = EmpresaAnalisada::query()->findOrFail($this->empresaId);

        if (Personificacao::ativa() !== null) {
            $this->meses = $empresa->renovacao_diagnostico_meses;

            return;
        }

        $this->validate(['meses' => ['required', 'integer', Rule::in(RenovacaoDeDiagnostico::PRAZOS)]], ['meses.*' => 'Escolha 3, 6, 9 ou 12 meses.']);

        $renovacao->mudarPrazo($empresa, $this->meses);

        $usuarioId = (string) Auth::id();
        AuditLogger::log(
            action: 'empresa.prazo_renovacao_alterado',
            subjectType: 'EmpresaAnalisada',
            subjectId: $empresa->id,
            actorType: 'user',
            actorId: $usuarioId,
            usuarioId: $usuarioId,
            context: ['meses' => $this->meses],
        );

        $this->salvo = true;
        $this->dispatch('prazo-renovacao-alterado');
    }

    public function render(): View
    {
        return view('livewire.empresa.prazo-renovacao-diagnostico', [
            'prazos' => RenovacaoDeDiagnostico::PRAZOS,
            'personificando' => Personificacao::ativa() !== null,
        ]);
    }
}
