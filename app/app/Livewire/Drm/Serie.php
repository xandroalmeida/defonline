<?php

declare(strict_types=1);

namespace App\Livewire\Drm;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\ConteudoDrm;
use App\Domain\Drm\Serie\SerieDrm;
use App\Domain\Drm\Setor;
use App\Models\DrmPreferencia;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Support\Drm\HistoricoDrm;
use App\Support\Drm\SerieVisao;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Série histórica (STORY-051, spec `STORY-046-serie-historica`) — tela de leitura.
 *
 * **Lê as versões vigentes, nunca recalcula nem persiste comparativo (ADR-007, CA-10).** Os
 * valores são os do snapshot de cada mês; os comparativos são derivados aqui, a cada leitura,
 * por {@see SerieDrm}. Mês sem lançamento é uma linha de ausência, jamais
 * um zero (CA-2). Empresa de outro usuário é 404 (IDR-009), pelo route binding com Global Scope.
 */
#[Layout('components.layouts.app')]
final class Serie extends Component
{
    public EmpresaAnalisada $empresa;

    public function mount(EmpresaAnalisada $empresa): void
    {
        $this->empresa = $empresa;
    }

    public function render(): View
    {
        $conteudo = app(ConteudoDrm::class);
        $setor = Setor::doCnae($this->empresa->cnae);
        $posicao = DrmPreferencia::daEmpresa($this->empresa)->posicao_prolabore;

        // Só rascunho de lançamento NOVO: o de retificação é de um mês que já está lançado.
        $rascunhos = [];
        foreach (DrmRascunho::query()->where('empresa_analisada_id', $this->empresa->id)->ativos()->where('origem', 'inicial')->get() as $r) {
            $rascunhos[Competencia::deData($r->competencia)->texto()] = [
                'bloco' => (int) ($r->rascunho_payload['bloco'] ?? 0) ?: null,
            ];
        }

        $visao = SerieVisao::de(
            $this->empresa,
            HistoricoDrm::serie($this->empresa, $posicao),
            $rascunhos,
            $conteudo->nomesDosBlocos($setor->value),
            CarbonImmutable::now(),
            $conteudo,
        );

        return view('livewire.drm.serie', [
            'visao' => $visao,
            'setorRotulo' => match ($setor) {
                Setor::Industria => 'Indústria',
                Setor::Comercio => 'Comércio',
                Setor::Servicos => 'Serviços',
            },
        ])->layoutData([
            'title' => (string) config('drm.serie.titulo'),
            'breadcrumb' => [
                ['label' => 'Minhas Empresas', 'url' => route('home')],
                ['label' => $this->empresa->nomeExibicao(), 'url' => route('empresas.show', $this->empresa)],
                ['label' => 'Meses', 'url' => '#'],
            ],
        ]);
    }
}
