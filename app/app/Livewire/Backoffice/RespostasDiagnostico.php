<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\Diagnostico;
use App\Models\Scopes\BelongsToUsuarioScope;
use App\Observabilidade\AuditLogger;
use App\Support\PermissaoAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-105 — respostas do quiz de um diagnóstico, como o cliente enviou, e os avisos de consistência
 * que ele aceitou. Dado financeiro do cliente: permissão própria e cada abertura auditada. CPF de
 * sócio sai mascarado.
 */
#[Layout('components.layouts.app')]
final class RespostasDiagnostico extends Component
{
    use ExigePermissao;

    public string $diagnosticoId;

    public function mount(string $diagnostico): void
    {
        $registro = self::buscar($diagnostico);
        $this->diagnosticoId = $registro->id;

        $atorId = (string) Auth::id();
        AuditLogger::log(
            action: 'backoffice.respostas_quiz_vistas',
            subjectType: 'Diagnostico',
            subjectId: $registro->id,
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
        );
    }

    public function render(): View
    {
        $diagnostico = self::buscar($this->diagnosticoId);
        $diagnostico->load([
            'usuario' => fn ($q) => $q->withTrashed(),
            'empresa' => fn ($q) => $q->withoutGlobalScope(BelongsToUsuarioScope::class)->withTrashed(),
        ]);

        return view('livewire.backoffice.respostas-diagnostico', [
            'diagnostico' => $diagnostico,
            'payload' => (array) $diagnostico->quiz_payload,
            'veFichas' => Auth::user()?->podeUsarBackoffice(PermissaoAdmin::VerUsuarios) ?? false,
        ])->layoutData(['title' => 'Respostas do quiz']);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::VerRespostasQuiz;
    }

    private static function buscar(string $id): Diagnostico
    {
        return Diagnostico::withoutGlobalScope(BelongsToUsuarioScope::class)->findOrFail($id);
    }
}
