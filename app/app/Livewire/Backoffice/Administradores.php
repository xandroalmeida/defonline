<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Support\PermissaoAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-067 / PDR-013 — dá e tira permissões de admin de contas já cadastradas.
 * Quem está em BACKOFFICE_EMAILS não muda por aqui, e ninguém altera a si mesmo.
 */
#[Layout('components.layouts.app')]
final class Administradores extends Component
{
    use ExigePermissao;

    public string $email = '';

    /** @var list<string> */
    public array $permissoes = [];

    public function mount(): void
    {
        $atorId = (string) Auth::id();

        AuditLogger::log(
            action: 'backoffice.admins_listados',
            subjectType: 'Usuario',
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
        );
    }

    public function salvar(): void
    {
        $this->email = trim($this->email);

        $this->validate([
            'email' => ['required', 'string', 'email:rfc'],
            'permissoes' => ['required', 'array', 'min:1'],
            'permissoes.*' => [Rule::enum(PermissaoAdmin::class)],
        ], [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'permissoes.required' => 'Selecione ao menos uma permissão.',
        ]);

        $alvo = Usuario::query()->whereRaw('lower(email) = ?', [mb_strtolower($this->email)])->first();

        if ($alvo === null || $alvo->anonimizado()) {
            throw ValidationException::withMessages(['email' => 'Nenhuma conta ativa com esse e-mail. A pessoa precisa se cadastrar antes.']);
        }

        // Conta sem e-mail confirmado pode não ser de quem parece: não recebe acesso.
        if (! $alvo->emailConfirmado()) {
            throw ValidationException::withMessages(['email' => 'Essa conta ainda não confirmou o e-mail.']);
        }

        $this->alterar($alvo, $this->comDependencias($this->permissoes));
        $this->limpar();
    }

    public function editar(string $usuarioId): void
    {
        $alvo = Usuario::query()->findOrFail($usuarioId);

        $this->resetValidation();
        $this->email = $alvo->email;
        $this->permissoes = $alvo->permissoes_admin ?? [];
    }

    public function remover(string $usuarioId): void
    {
        $this->alterar(Usuario::query()->findOrFail($usuarioId), null);
        $this->limpar();
    }

    public function limpar(): void
    {
        $this->reset('email', 'permissoes');
        $this->resetValidation();
    }

    public function render(): View
    {
        $emailsConfiguracao = (array) config('backoffice.equipe_emails', []);

        return view('livewire.backoffice.administradores', [
            'admins' => Usuario::query()
                ->where(function ($q) use ($emailsConfiguracao): void {
                    $q->whereNotNull('permissoes_admin')->orWhereIn(DB::raw('lower(email)'), $emailsConfiguracao);
                })
                ->orderBy('nome')
                ->get(),
            'opcoes' => PermissaoAdmin::cases(),
            'eu' => Auth::id(),
        ])->layoutData(['title' => 'Administradores']);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::GerenciarAdmins;
    }

    /**
     * O botão "Logar como" fica na lista de usuários, então essa permissão traz a de ver.
     *
     * @param  array<array-key, string>  $selecionadas
     * @return list<string>
     */
    private function comDependencias(array $selecionadas): array
    {
        if (in_array(PermissaoAdmin::LogarComo->value, $selecionadas, true)) {
            $selecionadas[] = PermissaoAdmin::VerUsuarios->value;
        }

        return array_values(array_filter(
            array_map(fn (PermissaoAdmin $p): string => $p->value, PermissaoAdmin::cases()),
            fn (string $p): bool => in_array($p, $selecionadas, true),
        ));
    }

    /** @param  list<string>|null  $depois */
    private function alterar(Usuario $alvo, ?array $depois): void
    {
        if ($alvo->id === Auth::id()) {
            throw ValidationException::withMessages(['email' => 'Você não pode alterar as próprias permissões.']);
        }

        if ($alvo->ehAdminPorConfiguracao()) {
            throw ValidationException::withMessages(['email' => 'As permissões dessa pessoa vêm da configuração do servidor.']);
        }

        $antes = $alvo->permissoes_admin;
        $alvo->forceFill(['permissoes_admin' => $depois])->save();

        $atorId = (string) Auth::id();
        AuditLogger::log(
            action: 'admin.permissoes_alteradas',
            subjectType: 'Usuario',
            subjectId: $alvo->id,
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
            before: ['permissoes' => $antes],
            after: ['permissoes' => $depois],
        );
    }
}
