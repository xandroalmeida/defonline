<?php

declare(strict_types=1);

namespace App\Livewire\Home;

use App\Domain\Cobranca\Carteira;
use App\Models\CobrancaConfig;
use App\Models\EmpresaAnalisada;
use App\Models\TrialConfig;
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
    /**
     * STORY-074 — aviso do teste em andamento, ou a oferta para quem ainda pode ativá-lo. Cobrança
     * desligada (STORY-073 CA-1): nada disso existe.
     *
     * @return array{avisoTrial: array{severidade: 'info'|'bloqueio', texto: string, acao: string, rotulo: string}|null, ofertaTrial: ?TrialConfig}
     */
    private function trial(Usuario $usuario): array
    {
        if (! CobrancaConfig::ligada()) {
            return ['avisoTrial' => null, 'ofertaTrial' => null];
        }

        $carteira = app(Carteira::class);
        $aviso = $carteira->avisoDoTeste($usuario->id);

        return [
            'avisoTrial' => $aviso,
            'ofertaTrial' => $aviso === null && $carteira->trialElegivel($usuario->id) ? TrialConfig::query()->first() : null,
        ];
    }

    public function render(): View
    {
        /** @var Usuario $usuario */
        $usuario = Auth::user();

        $empresas = EmpresaAnalisada::query()
            ->withUltimoDiagnosticoId()
            ->orderBy('created_at')
            ->get();

        return view('livewire.home.minhas-empresas', [
            'usuario' => $usuario,
            'empresas' => $empresas,
            ...$this->trial($usuario),
        ])->layoutData(['title' => 'Minhas Empresas']);
    }
}
