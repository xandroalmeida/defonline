<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\EmpresaAnalisada;
use App\Models\Scopes\BelongsToUsuarioScope;
use App\Observabilidade\AuditLogger;
use App\Support\BuscaBackoffice;
use App\Support\PermissaoAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * STORY-104 — empresas cadastradas por todos os usuários, com busca por nome, CNPJ/CPF, e-mail ou
 * telefone. Só leitura; leva à ficha da empresa. Mesma permissão da lista de usuários.
 */
#[Layout('components.layouts.app')]
final class Empresas extends Component
{
    use ExigePermissao;
    use WithPagination;

    public const POR_PAGINA = 50;

    #[Url(as: 'q', except: '')]
    public string $busca = '';

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
        $query = EmpresaAnalisada::withoutGlobalScope(BelongsToUsuarioScope::class)
            ->with(['usuario' => fn ($q) => $q->withTrashed()])
            ->withCount(['diagnosticos' => fn ($q) => $q->withoutGlobalScope(BelongsToUsuarioScope::class)]);

        $busca = new BuscaBackoffice($this->busca);
        if (! $busca->vazia()) {
            $query->where(function (Builder $q) use ($busca): void {
                $q->where('razao_social', 'ilike', $busca->padrao)
                    ->orWhere('nome_fantasia', 'ilike', $busca->padrao)
                    ->orWhere('nome_fantasia_informado', 'ilike', $busca->padrao)
                    ->orWhere('email', 'ilike', $busca->padrao);

                if ($busca->digitos !== null) {
                    $q->orWhere('documento', 'like', '%'.$busca->digitos.'%')
                        ->orWhere('telefone', 'like', '%'.$busca->digitos.'%');
                }
            });
        }

        $empresas = $query->latest()->orderByDesc('id')->paginate(self::POR_PAGINA);

        return view('livewire.backoffice.empresas', [
            'empresas' => $empresas,
        ])->layoutData(['title' => 'Empresas cadastradas']);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::VerUsuarios;
    }

    private function registrarAcesso(): void
    {
        $atorId = (string) Auth::id();

        AuditLogger::log(
            action: 'backoffice.empresas_listadas',
            subjectType: 'EmpresaAnalisada',
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
            context: ['busca' => (new BuscaBackoffice($this->busca))->paraAuditoria()],
        );
    }
}
