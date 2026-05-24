<?php

declare(strict_types=1);

namespace App\Livewire\Home;

use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tela "Minhas Empresas" (STORY-016 CA-1, CA-2, CA-5).
 *
 * Substitui a `/home` mínima da STORY-011: lista as Empresas Analisadas do
 * Usuário autenticado com badge da fonte de enriquecimento e botão placeholder
 * "Iniciar diagnóstico" (EPIC-002 ativa). Estado vazio leva para `/empresas/nova`.
 *
 * Multi-tenancy preservada via Global Scope do model + uso direto de
 * `auth()->user()` — input externo (URL, request) não influencia a query.
 *
 * Acesso à `/home` é leitura — não gera entrada em `audit_logs` (STORY-016 CA-5;
 * espec exige audit para escrita).
 */
#[Layout('components.layouts.app')]
final class MinhasEmpresas extends Component
{
    public function render(): View
    {
        /** @var Usuario $usuario */
        $usuario = Auth::user();

        $empresas = EmpresaAnalisada::query()
            ->orderBy('created_at')
            ->get();

        return view('livewire.home.minhas-empresas', [
            'usuario' => $usuario,
            'empresas' => $empresas,
        ])->layoutData(['title' => 'Minhas Empresas']);
    }
}
