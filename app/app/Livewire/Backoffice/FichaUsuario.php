<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Domain\Cobranca\StatusLote;
use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\Assinatura;
use App\Models\DemandaCaptacao;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\LoteCredito;
use App\Models\Scopes\BelongsToUsuarioScope;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Support\PermissaoAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-104 — ficha do usuário no backoffice: dados com CPF completo, contato, plano e créditos,
 * empresas e diagnósticos. Só leitura; cada abertura é auditada. Lê sem o Global Scope de tenant
 * (quem consulta é a equipe, não o dono).
 */
#[Layout('components.layouts.app')]
final class FichaUsuario extends Component
{
    use ExigePermissao;

    public string $usuarioId;

    public function mount(string $usuario): void
    {
        $registro = Usuario::withTrashed()->findOrFail($usuario);
        $this->usuarioId = $registro->id;

        $atorId = (string) Auth::id();
        AuditLogger::log(
            action: 'backoffice.usuario_visto',
            subjectType: 'Usuario',
            subjectId: $registro->id,
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
        );
    }

    public function render(): View
    {
        $usuario = Usuario::withTrashed()->findOrFail($this->usuarioId);

        $empresas = EmpresaAnalisada::withoutGlobalScopes()->withTrashed()
            ->where('usuario_id', $usuario->id)
            ->withCount(['diagnosticos' => fn ($q) => $q->withoutGlobalScope(BelongsToUsuarioScope::class)])
            ->orderBy('razao_social')
            ->get();

        $diagnosticos = Diagnostico::withoutGlobalScope(BelongsToUsuarioScope::class)
            ->with(['empresa' => fn ($q) => $q->withoutGlobalScopes()->withTrashed()])
            ->where('usuario_id', $usuario->id)
            ->latest('gerado_em')
            ->get();

        $assinatura = Assinatura::query()->with('plano')->where('usuario_id', $usuario->id)->latest('data_inicio')->first();

        $creditos = (int) LoteCredito::query()
            ->where('usuario_id', $usuario->id)
            ->where('status', StatusLote::Ativo->value)
            ->where('data_expiracao', '>', now())
            ->sum('quantidade_restante');

        return view('livewire.backoffice.ficha-usuario', [
            'usuario' => $usuario,
            'admin' => Auth::user(),
            'empresas' => $empresas,
            'diagnosticos' => $diagnosticos,
            'assinatura' => $assinatura,
            'creditos' => $creditos,
            'demandas' => DemandaCaptacao::query()->where('usuario_id', $usuario->id)->pluck('id', 'diagnostico_id'),
        ])->layoutData(['title' => $usuario->anonimizado() ? 'Usuário anonimizado' : $usuario->nome]);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::VerUsuarios;
    }
}
