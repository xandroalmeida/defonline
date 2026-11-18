<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\DemandaCaptacao;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Scopes\BelongsToUsuarioScope;
use App\Observabilidade\AuditLogger;
use App\Support\PermissaoAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-104 — ficha da empresa no backoffice: documento, contato com link do WhatsApp, usuário dono,
 * diagnósticos e demanda de captação. Só leitura; cada abertura é auditada.
 */
#[Layout('components.layouts.app')]
final class FichaEmpresa extends Component
{
    use ExigePermissao;

    public string $empresaId;

    public function mount(string $empresa): void
    {
        $registro = self::buscar($empresa);
        $this->empresaId = $registro->id;

        $atorId = (string) Auth::id();
        AuditLogger::log(
            action: 'backoffice.empresa_vista',
            subjectType: 'EmpresaAnalisada',
            subjectId: $registro->id,
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
        );
    }

    public function render(): View
    {
        $empresa = self::buscar($this->empresaId);
        $empresa->load(['usuario' => fn ($q) => $q->withTrashed()]);

        return view('livewire.backoffice.ficha-empresa', [
            'empresa' => $empresa,
            'diagnosticos' => Diagnostico::withoutGlobalScope(BelongsToUsuarioScope::class)
                ->where('empresa_analisada_id', $empresa->id)
                ->latest('gerado_em')
                ->get(),
            'demandas' => DemandaCaptacao::query()->where('empresa_analisada_id', $empresa->id)->pluck('id', 'diagnostico_id'),
        ])->layoutData(['title' => $empresa->nomeExibicao()]);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::VerUsuarios;
    }

    private static function buscar(string $id): EmpresaAnalisada
    {
        return EmpresaAnalisada::withoutGlobalScope(BelongsToUsuarioScope::class)->withTrashed()->findOrFail($id);
    }
}
