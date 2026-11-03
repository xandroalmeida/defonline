<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * STORY-065 — backoffice: lista de usuários cadastrados (só-leitura, PDR-011).
 *
 * Acesso garantido pelo middleware `backoffice` (allowlist, 404 para os demais).
 * Contagens e último login vêm de subqueries na própria consulta da página
 * (CA-5: o número de queries não cresce com a página). As subqueries leem as
 * tabelas direto, sem o Global Scope de tenant de EmpresaAnalisada/Diagnostico,
 * que filtraria pelo usuário logado (o membro da equipe).
 */
#[Layout('components.layouts.app')]
final class Usuarios extends Component
{
    use WithPagination;

    public const POR_PAGINA = 50;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    /** Termo digitado; só vira `$busca` ao enviar (uma linha de audit por busca). */
    public string $termo = '';

    public function mount(): void
    {
        $this->termo = $this->busca;
        $this->registrarAcesso();
    }

    public function buscar(): void
    {
        $this->busca = trim($this->termo);
        $this->resetPage();
        $this->registrarAcesso();
    }

    public function render(): View
    {
        $base = $this->filtrar(Usuario::withTrashed());

        $totais = (clone $base)->toBase()
            ->selectRaw('count(*) as total, count(email_confirmed_at) as confirmados')
            ->first();

        return view('livewire.backoffice.usuarios', [
            'usuarios' => $this->paginar($base),
            'total' => (int) ($totais->total ?? 0),
            'confirmados' => (int) ($totais->confirmados ?? 0),
        ])->layoutData(['title' => 'Usuários cadastrados']);
    }

    /**
     * @param  Builder<Usuario>  $query
     * @return Builder<Usuario>
     */
    private function filtrar(Builder $query): Builder
    {
        if ($this->busca === '') {
            return $query;
        }

        $padrao = '%'.addcslashes($this->busca, '%_\\').'%';

        return $query->where(function (Builder $q) use ($padrao): void {
            $q->where('nome', 'ilike', $padrao)->orWhere('email', 'ilike', $padrao);
        });
    }

    /**
     * @param  Builder<Usuario>  $query
     * @return LengthAwarePaginator<int, Usuario>
     */
    private function paginar(Builder $query): LengthAwarePaginator
    {
        return $query
            ->select('usuarios.*')
            ->selectSub(
                DB::table('empresas_analisadas')->selectRaw('count(*)')
                    ->whereColumn('empresas_analisadas.usuario_id', 'usuarios.id')
                    ->whereNull('empresas_analisadas.deleted_at'),
                'empresas_count',
            )
            ->selectSub(
                DB::table('diagnosticos')->selectRaw('count(*)')
                    ->whereColumn('diagnosticos.usuario_id', 'usuarios.id')
                    ->whereNull('diagnosticos.deleted_at'),
                'diagnosticos_count',
            )
            ->selectSub(
                DB::table('audit_logs')->selectRaw('max(occurred_at)')
                    ->whereColumn('audit_logs.usuario_id', 'usuarios.id')
                    ->where('audit_logs.action', 'usuario.login_sucesso'),
                'ultimo_login_at',
            )
            ->orderByDesc('usuarios.created_at')
            ->orderByDesc('usuarios.id')
            ->paginate(self::POR_PAGINA);
    }

    /** CA-6 — ator e termo de busca; sem PII de terceiros. */
    private function registrarAcesso(): void
    {
        $atorId = (string) Auth::id();

        AuditLogger::log(
            action: 'backoffice.usuarios_listados',
            subjectType: 'Usuario',
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
            context: ['busca' => $this->busca === '' ? null : $this->busca],
        );
    }
}
