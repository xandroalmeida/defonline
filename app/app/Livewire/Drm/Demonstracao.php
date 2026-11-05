<?php

declare(strict_types=1);

namespace App\Livewire\Drm;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Setor;
use App\Models\DrmLancamento;
use App\Models\DrmPreferencia;
use App\Models\EmpresaAnalisada;
use App\Support\Drm\ComparativosVisao;
use App\Support\Drm\DemonstracaoVisao;
use App\Support\Drm\HistoricoDrm;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Demonstração do Mês (STORY-049, spec `STORY-046-demonstracao-mes`) — tela de leitura.
 *
 * **Lê o snapshot, nunca recalcula (ADR-007, CA-8).** O motor não é injetado aqui: o que a tela
 * exibe é `drm_lancamentos.demonstracao` da versão vigente, com a versão do motor e a data
 * que o próprio snapshot carrega. Trocar o modo do pró-labore (CA-3b) escolhe o outro array do
 * snapshot e grava `posicao_prolabore` em `drm_preferencias`; nada é recalculado nem migrado, e a
 * preferência vale para toda a série.
 *
 * Empresa de outro usuário e competência sem lançamento são o mesmo 404 (IDR-009): a empresa
 * resolvida pelo route binding e a consulta do lançamento passam pelo Global Scope do usuário.
 */
#[Layout('components.layouts.app')]
final class Demonstracao extends Component
{
    public EmpresaAnalisada $empresa;

    /** `AAAA-MM`. */
    #[Locked]
    public string $competencia = '';

    /** `fora` ou `dentro`: a leitura do pró-labore (PDR-004 decisão B). */
    public string $posicao = 'fora';

    public function mount(EmpresaAnalisada $empresa, string $competencia): void
    {
        $this->empresa = $empresa;
        $this->competencia = Competencia::deTexto($competencia)->texto();
        abort_if($this->lancamento() === null, 404);

        $this->posicao = DrmPreferencia::daEmpresa($empresa)->posicao_prolabore;
    }

    /** Alterna entre "retirada fora" e "retirada dentro". Só preferência: não toca o lançamento. */
    public function trocarPosicao(): void
    {
        $this->posicao = $this->posicao === 'fora' ? 'dentro' : 'fora';
        DrmPreferencia::gravarPosicao($this->empresa, $this->posicao);
    }

    public function render(): View
    {
        $lancamento = $this->lancamento();
        abort_if($lancamento === null, 404);

        $competencia = Competencia::deTexto($this->competencia);
        $titulo = ucfirst($competencia->mesPorExtenso()).' de '.$competencia->data->year;
        $visao = DemonstracaoVisao::de($lancamento->demonstracao->getArrayCopy(), $this->posicao);

        return view('livewire.drm.demonstracao', [
            'visao' => $visao,
            'titulo' => $titulo,
            'setorRotulo' => match (Setor::tryFrom($lancamento->setor)) {
                Setor::Industria => 'Indústria',
                Setor::Comercio => 'Comércio',
                Setor::Servicos => 'Serviços',
                null => $lancamento->setor,
            },
            'ressalvas' => DemonstracaoVisao::ressalvas($lancamento->alertas_aceitos),
            // CA-4: tudo da linha vigente — versao > 1, versao - 1 e gerado_em.
            'retificado' => $lancamento->foiRetificado(),
            'vezesRetificado' => $lancamento->vezesRetificado(),
            'retificadoEm' => $lancamento->retificadoEm(),
            'modo' => config("drm.tela.modo.{$visao->posicao}"),
            // STORY-051 CA-3: os quatro comparativos por linha de nível 1, derivados das versões vigentes.
            'comparativos' => ComparativosVisao::dasLinhas(HistoricoDrm::serie($this->empresa, $visao->posicao), $competencia, $visao->linhas),
        ])->layoutData([
            'title' => $titulo,
            'breadcrumb' => [
                ['label' => 'Minhas Empresas', 'url' => route('home')],
                ['label' => $this->empresa->nomeExibicao(), 'url' => route('empresas.show', $this->empresa)],
                ['label' => $titulo, 'url' => '#'],
            ],
        ]);
    }

    private function lancamento(): ?DrmLancamento
    {
        return DrmLancamento::vigenteDaCompetencia($this->empresa, Competencia::deTexto($this->competencia)->dia01());
    }
}
