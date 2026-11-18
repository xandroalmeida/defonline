<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\CaptacaoConfig;
use App\Models\DemandaCaptacao;
use App\Observabilidade\AuditLogger;
use App\Support\PermissaoAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * STORY-102 / PDR-021 — e-mail da equipe que recebe as demandas de captação e a lista das demandas.
 * Sem e-mail cadastrado, nenhuma demanda é enviada por e-mail; a lista continua mostrando todas.
 */
#[Layout('components.layouts.app')]
final class Captacao extends Component
{
    use ExigePermissao;
    use WithPagination;

    public const POR_PAGINA = 50;

    public string $email = '';

    public bool $salvo = false;

    public function mount(): void
    {
        $this->email = (string) CaptacaoConfig::atual()->email;
        $this->auditar('backoffice.captacao_listada');
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
        $this->auditar('captacao.email_alterado', $config->id, ['email' => $antes], ['email' => $depois]);
        $this->salvo = true;
    }

    public function updatedEmail(): void
    {
        $this->salvo = false;
    }

    public function render(): View
    {
        return view('livewire.backoffice.captacao', [
            'demandas' => DemandaCaptacao::query()
                ->with(['usuario', 'empresa'])
                ->latest()
                ->paginate(self::POR_PAGINA),
            'emailCadastrado' => CaptacaoConfig::emailDaEquipe(),
        ])->layoutData(['title' => 'Captação']);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::GerenciarCaptacao;
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    private function auditar(string $action, ?string $subjectId = null, ?array $antes = null, ?array $depois = null): void
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
