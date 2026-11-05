<?php

declare(strict_types=1);

namespace App\Livewire\Empresa;

use App\Domain\TipoDocumento;
use App\Models\EmpresaAnalisada;
use App\Observabilidade\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Nome fantasia editado no próprio lugar em `/empresas/{id}` (PDR-017). Em CNPJ,
 * o valor da Receita fica intacto em `nome_fantasia` e o informado vai para
 * coluna própria; em CPF, `nome_fantasia` já é o nome comercial do autônomo.
 *
 * A empresa é recarregada pelo Global Scope a cada ação, como em {@see Observacoes}.
 */
final class NomeFantasia extends Component
{
    private const FLASH_SALVO = 'empresa_nome_fantasia_salvo';

    #[Locked]
    public string $empresaId;

    #[Locked]
    public bool $pessoaFisica = false;

    /** Valor gravado, exibido fora do modo de edição. */
    #[Locked]
    public string $nomeAtual = '';

    /** Valor da Receita, mostrado quando o informado o substitui. */
    #[Locked]
    public ?string $nomeDaReceita = null;

    #[Locked]
    public string $textoVazio = '—';

    public string $nomeFantasia = '';

    public bool $editando = false;

    public bool $salvo = false;

    public function mount(EmpresaAnalisada $empresa): void
    {
        $this->empresaId = $empresa->id;
        $this->pessoaFisica = $empresa->tipo_documento === TipoDocumento::Cpf;
        $this->nomeAtual = (string) $empresa->nomeFantasiaVigente();
        $this->nomeFantasia = $this->nomeAtual;
        $this->nomeDaReceita = $empresa->nome_fantasia_informado ? $empresa->nome_fantasia : null;
        $this->textoVazio = $empresa->fichaRfb() !== null ? 'Não informado pela Receita' : '—';
        $this->salvo = session()->has(self::FLASH_SALVO);
    }

    public function editar(): void
    {
        $this->nomeFantasia = $this->nomeAtual;
        $this->salvo = false;
        $this->editando = true;
    }

    public function cancelar(): void
    {
        $this->nomeFantasia = $this->nomeAtual;
        $this->resetErrorBag();
        $this->editando = false;
    }

    public function salvar(): mixed
    {
        $this->salvo = false;

        $this->validate(
            ['nomeFantasia' => ['nullable', 'string', 'max:255']],
            ['nomeFantasia.max' => 'O nome pode ter até 255 caracteres.'],
        );

        $empresa = EmpresaAnalisada::query()->findOrFail($this->empresaId);
        $digitado = trim($this->nomeFantasia);

        if ($this->pessoaFisica) {
            $empresa->nome_fantasia = $digitado === '' ? null : $digitado;
        } else {
            $empresa->nome_fantasia_informado = EmpresaAnalisada::nomeFantasiaInformado($digitado, $empresa->nome_fantasia);
        }

        if (! $empresa->isDirty()) {
            $this->cancelar();

            return null;
        }

        $empresa->save();

        $usuarioId = (string) Auth::id();
        // Só o fato: em CPF o nome pode identificar a pessoa.
        AuditLogger::log(
            action: 'empresa.nome_fantasia_alterado',
            subjectType: 'EmpresaAnalisada',
            subjectId: $empresa->id,
            actorType: 'user',
            actorId: $usuarioId,
            usuarioId: $usuarioId,
        );

        // O nome também está no título e no breadcrumb, fora deste componente.
        session()->flash(self::FLASH_SALVO, true);

        return $this->redirect(route('empresas.show', $empresa), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.empresa.nome-fantasia');
    }
}
