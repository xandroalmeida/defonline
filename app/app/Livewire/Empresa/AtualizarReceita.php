<?php

declare(strict_types=1);

namespace App\Livewire\Empresa;

use App\Domain\FonteEnriquecimento;
use App\Domain\Rfb\RfbCnpjStatus;
use App\Domain\TipoDocumento;
use App\Models\EmpresaAnalisada;
use App\Services\Rfb\AtualizarFichaRfb;
use App\Services\Rfb\RfbCnpjFalhouException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ação "Atualizar dados da Receita" em `/empresas/{id}` (STORY-064 CA-1..CA-5).
 *
 * Só renderiza para empresa com CNPJ. O resultado de sucesso volta por redirect
 * (o título, o breadcrumb e a ficha mudam); a falha fica aqui, sem tocar a ficha.
 */
final class AtualizarReceita extends Component
{
    private const FLASH = 'empresa_receita_atualizacao';

    #[Locked]
    public string $empresaId;

    #[Locked]
    public bool $cnpj = false;

    /** Empresa legada: manual ou sem a ficha completa (CA-2). */
    #[Locked]
    public bool $legada = false;

    #[Locked]
    public ?string $consultadoEm = null;

    /** 'atualizada' | 'ja_atualizada' | null, vindo do redirect. */
    #[Locked]
    public ?string $resultado = null;

    public ?string $erro = null;

    public function mount(EmpresaAnalisada $empresa): void
    {
        $this->empresaId = $empresa->id;
        $this->cnpj = $empresa->tipo_documento === TipoDocumento::Cnpj;
        $this->legada = $this->cnpj
            && ($empresa->fonte_enriquecimento === FonteEnriquecimento::Manual || $empresa->ficha_rfb === null);
        $this->consultadoEm = $this->cnpj ? $empresa->enriquecido_at?->format('d/m/Y') : null;
        $this->resultado = session()->get(self::FLASH);
    }

    public function atualizar(AtualizarFichaRfb $atualizar): mixed
    {
        $this->erro = null;
        $empresa = EmpresaAnalisada::query()->findOrFail($this->empresaId);

        if ($empresa->tipo_documento !== TipoDocumento::Cnpj) {
            return null;
        }

        try {
            $alterou = $atualizar->executar($empresa);
        } catch (RfbCnpjFalhouException $falha) {
            $this->erro = $falha->status === RfbCnpjStatus::CnpjInexistente
                ? Cadastrar::MSG_CNPJ_INEXISTENTE
                : Cadastrar::MSG_RECEITA_INDISPONIVEL;

            return null;
        }

        session()->flash(self::FLASH, $alterou ? 'atualizada' : 'ja_atualizada');

        return $this->redirect(route('empresas.show', $empresa), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.empresa.atualizar-receita');
    }
}
