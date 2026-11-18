<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\Cupom;
use App\Observabilidade\AuditLogger;
use App\Services\Cobranca\RelatorioClientesDoCupom;
use App\Support\PermissaoAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Clientes que vieram por um cupom, para acompanhar uma parceria. Abrir é auditado (dado pessoal). */
#[Layout('components.layouts.app')]
final class ClientesDoCupom extends Component
{
    use ExigePermissao;

    public string $cupomId;

    public function mount(string $cupom): void
    {
        $this->cupomId = Cupom::query()->findOrFail($cupom)->id;

        $atorId = (string) Auth::id();
        AuditLogger::log(
            action: 'backoffice.cupom_clientes_vistos',
            subjectType: 'Cupom',
            subjectId: $this->cupomId,
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
        );
    }

    public function render(RelatorioClientesDoCupom $relatorio): View
    {
        $cupom = Cupom::query()->findOrFail($this->cupomId);
        $linhas = $relatorio->linhas($cupom);

        return view('livewire.backoffice.clientes-do-cupom', [
            'cupom' => $cupom,
            'linhas' => $linhas,
            'totais' => RelatorioClientesDoCupom::totais($linhas),
            'veFichas' => Auth::user()?->podeUsarBackoffice(PermissaoAdmin::VerUsuarios) ?? false,
        ])->layoutData(['title' => "Clientes do cupom {$cupom->codigo}"]);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::GerenciarCupons;
    }
}
