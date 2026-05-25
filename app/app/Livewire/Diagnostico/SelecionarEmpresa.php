<?php

declare(strict_types=1);

namespace App\Livewire\Diagnostico;

use App\Models\EmpresaAnalisada;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tela "Selecione uma empresa" — entrada via item Diagnósticos do menu (STORY-027 CA-1).
 *
 * Quando Roberto chega aqui sem ter clicado em um card de empresa, listamos as
 * empresas dele para escolher a alvo do diagnóstico. Estado vazio leva para
 * `/empresas/nova`.
 */
#[Layout('components.layouts.app')]
final class SelecionarEmpresa extends Component
{
    public function render(): View
    {
        $empresas = EmpresaAnalisada::query()
            ->orderBy('created_at')
            ->get();

        return view('livewire.diagnostico.selecionar-empresa', [
            'empresas' => $empresas,
        ])->layoutData(['title' => 'Selecione uma empresa']);
    }
}
