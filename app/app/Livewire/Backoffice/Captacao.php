<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Domain\Captacao\StatusDemanda;
use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\CaptacaoConfig;
use App\Models\DemandaCaptacao;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Services\Captacao\GerirDemandaCaptacao;
use App\Support\PermissaoAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * STORY-102 / STORY-103 / PDR-021 — CRM básico das demandas de captação: quadro Kanban por etapa,
 * responsável, notas e linha do tempo, filtros e destaque do prazo vencido. Também guarda o e-mail
 * da equipe que recebe as demandas (sem e-mail, nenhuma é enviada por e-mail).
 */
#[Layout('components.layouts.app')]
final class Captacao extends Component
{
    use ExigePermissao;

    /** Encerradas são histórico: o quadro mostra só as mais recentes. */
    public const ENCERRADAS_NO_QUADRO = 30;

    public string $email = '';

    public bool $salvo = false;

    /** '' = todos · 'meus' · 'sem' (sem responsável) · id de um responsável. */
    #[Url(as: 'resp', except: '')]
    public string $responsavel = '';

    #[Url(as: 'q', except: '')]
    public string $busca = '';

    #[Url(as: 'sem_autorizacao', except: false)]
    public bool $mostrarSemAutorizacao = false;

    #[Url(as: 'demanda', except: null)]
    public ?string $selecionadaId = null;

    public string $nota = '';

    public function mount(): void
    {
        $this->email = (string) CaptacaoConfig::atual()->email;
        $this->registrar('backoffice.captacao_listada');
    }

    public function salvar(): void
    {
        $this->email = mb_strtolower(trim($this->email));

        $this->validate(
            ['email' => ['nullable', 'email', 'max:255']],
            ['email.email' => 'Informe um e-mail válido ou deixe em branco.', 'email.max' => 'O e-mail pode ter até 255 caracteres.'],
        );

        $config = CaptacaoConfig::atual();
        $antes = $config->email;
        $depois = $this->email === '' ? null : $this->email;

        $config->update(['email' => $depois, 'alterado_por_usuario_id' => Auth::id()]);
        $this->registrar('captacao.email_alterado', $config->id, ['email' => $antes], ['email' => $depois]);
        $this->salvo = true;
    }

    public function updatedEmail(): void
    {
        $this->salvo = false;
    }

    public function mover(string $id, string $status, GerirDemandaCaptacao $gerir): void
    {
        $para = StatusDemanda::tryFrom($status);
        abort_if($para === null, 422);

        $gerir->mover($this->demanda($id), $para, $this->autor());
    }

    public function atribuir(string $id, string $responsavelId, GerirDemandaCaptacao $gerir): void
    {
        $responsavel = null;

        if ($responsavelId !== '') {
            $responsavel = GerirDemandaCaptacao::responsaveisPossiveis()->firstWhere('id', $responsavelId);
            abort_if($responsavel === null, 422);
        }

        $gerir->atribuir($this->demanda($id), $responsavel, $this->autor());
    }

    public function anotar(GerirDemandaCaptacao $gerir): void
    {
        $this->nota = trim($this->nota);

        $this->validate(
            ['nota' => ['required', 'string', 'max:'.GerirDemandaCaptacao::NOTA_MAX]],
            ['nota.required' => 'Escreva a nota.', 'nota.max' => 'A nota pode ter até '.GerirDemandaCaptacao::NOTA_MAX.' caracteres.'],
        );

        $gerir->anotar($this->demanda((string) $this->selecionadaId), $this->nota, $this->autor());
        $this->nota = '';
    }

    public function abrir(string $id): void
    {
        $this->demanda($id);
        $this->selecionadaId = $id;
        $this->nota = '';
        $this->resetValidation('nota');
    }

    public function fechar(): void
    {
        $this->selecionadaId = null;
        $this->nota = '';
    }

    public function render(): View
    {
        $base = $this->filtrar(DemandaCaptacao::query()->with(['usuario', 'empresa', 'responsavel']));

        $colunas = [];
        foreach (StatusDemanda::cases() as $status) {
            $query = (clone $base)->where('status', $status->value);
            $colunas[$status->value] = [
                'status' => $status,
                'total' => (clone $query)->count(),
                'demandas' => $status === StatusDemanda::Encerrada
                    ? $query->orderByDesc('status_alterado_em')->limit(self::ENCERRADAS_NO_QUADRO)->get()
                    : $query->orderBy('created_at')->get(),
            ];
        }

        $selecionada = $this->selecionadaId === null ? null : DemandaCaptacao::query()
            ->with(['usuario', 'empresa', 'responsavel', 'eventos' => fn ($q) => $q->with('autor')->latest('created_at')])
            ->find($this->selecionadaId);

        return view('livewire.backoffice.captacao', [
            'colunas' => $colunas,
            'selecionada' => $selecionada,
            'responsaveis' => GerirDemandaCaptacao::responsaveisPossiveis(),
            'emailCadastrado' => CaptacaoConfig::emailDaEquipe(),
            'semAutorizacao' => DemandaCaptacao::query()->where('autoriza_contato', false)->count(),
        ])->layoutData(['title' => 'Captação']);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::GerenciarCaptacao;
    }

    /**
     * @param  Builder<DemandaCaptacao>  $query
     * @return Builder<DemandaCaptacao>
     */
    private function filtrar(Builder $query): Builder
    {
        if (! $this->mostrarSemAutorizacao) {
            $query->where('autoriza_contato', true);
        }

        match ($this->responsavel) {
            '' => null,
            'meus' => $query->where('responsavel_id', Auth::id()),
            'sem' => $query->whereNull('responsavel_id'),
            default => $query->where('responsavel_id', $this->responsavel),
        };

        $busca = trim($this->busca);
        if ($busca !== '') {
            $padrao = '%'.addcslashes($busca, '%_\\').'%';
            $digitos = preg_replace('/\D+/', '', $busca) ?? '';
            $query->whereHas('empresa', fn (Builder $q) => $q->withoutGlobalScopes()->where(fn (Builder $w) => $w
                ->where('razao_social', 'ilike', $padrao)
                ->orWhere('nome_fantasia', 'ilike', $padrao)
                ->orWhere('nome_fantasia_informado', 'ilike', $padrao)
                ->when($digitos !== '', fn (Builder $d) => $d->orWhere('documento', 'like', '%'.$digitos.'%'))));
        }

        return $query;
    }

    private function demanda(string $id): DemandaCaptacao
    {
        return DemandaCaptacao::query()->findOrFail($id);
    }

    private function autor(): Usuario
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof Usuario, 403);

        return $usuario;
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    private function registrar(string $action, ?string $subjectId = null, ?array $antes = null, ?array $depois = null): void
    {
        $atorId = (string) Auth::id();

        AuditLogger::log(
            action: $action,
            subjectType: 'CaptacaoConfig',
            subjectId: $subjectId,
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
            before: $antes,
            after: $depois,
        );
    }
}
