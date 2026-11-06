<?php

declare(strict_types=1);

namespace App\Livewire\Empresa;

use App\Models\EmpresaAnalisada;
use App\Services\Diagnostico\RenovacaoDeDiagnostico;
use App\Support\Personificacao;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/** STORY-099 CA-3 — alerta de prazo vencido, em Minhas Empresas e na página da empresa. */
final class AlertaRenovacaoDiagnostico extends Component
{
    #[Locked]
    public string $empresaId;

    /** Na página da empresa o alerta fica solto entre blocos e precisa de margem própria. */
    #[Locked]
    public bool $comMargem = false;

    public function mount(EmpresaAnalisada $empresa, bool $comMargem = false): void
    {
        $this->empresaId = $empresa->id;
        $this->comMargem = $comMargem;
    }

    public function dispensar(RenovacaoDeDiagnostico $renovacao): void
    {
        if (Personificacao::ativa() === null) {
            $renovacao->dispensarAlerta(EmpresaAnalisada::query()->findOrFail($this->empresaId));
        }
    }

    #[On('prazo-renovacao-alterado')]
    public function atualizar(): void
    {
        // Só provoca a nova renderização.
    }

    public function render(RenovacaoDeDiagnostico $renovacao): View
    {
        return view('livewire.empresa.alerta-renovacao-diagnostico', [
            'diagnostico' => $renovacao->diagnosticoComAlerta(EmpresaAnalisada::query()->findOrFail($this->empresaId)),
            'personificando' => Personificacao::ativa() !== null,
        ]);
    }
}
