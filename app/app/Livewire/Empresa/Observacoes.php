<?php

declare(strict_types=1);

namespace App\Livewire\Empresa;

use App\Models\EmpresaAnalisada;
use App\Observabilidade\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Observações do usuário sobre a Empresa Analisada, editáveis a qualquer
 * momento em `/empresas/{id}` (STORY-063 CA-5; PDR-010 item 4).
 *
 * É o único dado da empresa que o usuário altera depois do cadastro. A empresa
 * é recarregada pelo Global Scope a cada ação — o id vem `#[Locked]` e, mesmo
 * que fosse forjado, só resolveria empresas do próprio usuário.
 */
final class Observacoes extends Component
{
    #[Locked]
    public string $empresaId;

    public string $observacoes = '';

    public bool $salvo = false;

    public function mount(EmpresaAnalisada $empresa): void
    {
        $this->empresaId = $empresa->id;
        $this->observacoes = (string) $empresa->observacoes;
    }

    public function salvar(): void
    {
        $this->salvo = false;

        $this->validate(
            ['observacoes' => ['nullable', 'string', 'max:'.Cadastrar::OBSERVACOES_MAX]],
            ['observacoes.max' => 'As observações podem ter até '.Cadastrar::OBSERVACOES_MAX.' caracteres.'],
        );

        $empresa = EmpresaAnalisada::query()->findOrFail($this->empresaId);
        $texto = trim($this->observacoes);
        $novo = $texto === '' ? null : $texto;

        if ($novo !== $empresa->observacoes) {
            $empresa->update(['observacoes' => $novo]);

            $usuarioId = (string) Auth::id();
            // Só o fato e o tamanho — o texto é livre e pode conter PII.
            AuditLogger::log(
                action: 'empresa.observacoes_alteradas',
                subjectType: 'EmpresaAnalisada',
                subjectId: $empresa->id,
                actorType: 'user',
                actorId: $usuarioId,
                usuarioId: $usuarioId,
                context: ['tamanho' => mb_strlen((string) $novo)],
            );
        }

        $this->observacoes = (string) $novo;
        $this->salvo = true;
    }

    public function render(): View
    {
        return view('livewire.empresa.observacoes');
    }
}
