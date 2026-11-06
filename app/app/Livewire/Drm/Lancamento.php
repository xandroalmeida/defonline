<?php

declare(strict_types=1);

namespace App\Livewire\Drm;

use App\Actions\Drm\CompetenciaJaLancada;
use App\Actions\Drm\CompetenciaNaoLancada;
use App\Actions\Drm\FecharLancamento;
use App\Actions\Drm\RetificacaoConflitante;
use App\Actions\Drm\RetificacaoSemMudanca;
use App\Actions\Drm\RetificarLancamento;
use App\Domain\Drm\Alertas\AvaliadorDeAlertas;
use App\Domain\Drm\Competencia;
use App\Domain\Drm\ConteudoDrm;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento as LancamentoDrm;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Ocorrencia;
use App\Domain\Drm\PreenchimentoInicial;
use App\Domain\Drm\Setor;
use App\Domain\Drm\ValidaLancamento;
use App\Models\DrmLancamento;
use App\Models\DrmPreferencia;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Observabilidade\EventosDrm;
use App\Support\Cobranca\AcessoDrm;
use App\Support\DataExibicao;
use App\Support\Drm\HistoricoDrm;
use App\Support\Drm\MensagensDrm;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Lançamento mensal do DRM ("Raio X do Negócio") — STORY-047, spec `STORY-046-lancamento-mensal`.
 *
 * Seis blocos pelos grupos do Anexo K; o rascunho é salvo a cada bloco concluído
 * (`drm_rascunhos`, ADR-007) e o fechamento grava `versao = 1` em `drm_lancamentos`.
 * Multi-tenant: a empresa resolvida pelo route binding já passa pelo Global Scope,
 * então outra conta recebe 404 (IDR-009).
 *
 * **Retificação (STORY-050, spec §4.9).** Com `{competencia}` na rota (`drm.corrigir`) o mesmo
 * formulário corrige um mês já fechado: nasce semeado com a versão vigente (sem marca de herança),
 * guarda `origem = 'retificacao'` e `base_versao = N` no rascunho e, no fechamento, entrega a
 * {@see RetificarLancamento}. B1 não se aplica; as demais regras valem. A competência, o setor e
 * a versão-base são `#[Locked]`: o cliente não os troca. A retificação NÃO altera as preferências
 * de modo da empresa (um mês antigo não deve mudar o que o próximo mês abre).
 *
 * **Valores ficam como texto BRL** (o que a máscara mostra); a interpretação em
 * centavos e todas as regras vivem em `App\Domain\Drm`. Este componente só orquestra
 * estado, rascunho e navegação.
 */
#[Layout('components.layouts.app')]
final class Lancamento extends Component
{
    public const BLOCOS = 6;

    public EmpresaAnalisada $empresa;

    /** `AAAA-MM`. */
    public string $competencia = '';

    public int $bloco_atual = 1;

    /** @var array<string, string|null> campo => texto BRL */
    public array $valores = [];

    /** @var array<string, string> valor do Grupo => valor do Modo */
    public array $modos = [];

    /**
     * Campos que vieram do mês de origem e o usuário ainda não conferiu (DDR-003).
     *
     * @var list<string>
     */
    public array $repetidos = [];

    /**
     * Grupos cujo total foi preenchido pela soma dos itens: grupo => centavos.
     *
     * @var array<string, int>
     */
    public array $somados = [];

    /**
     * Herança parcial: grupo => total do mês de origem, quando ele era agregado.
     *
     * @var array<string, int>
     */
    public array $total_anterior = [];

    /** Competência de origem dos valores herdados (`AAAA-MM`), ou vazio. */
    public string $mes_origem = '';

    /** Grupo com a confirmação destrutiva aberta (agregado → detalhado). */
    public ?string $confirmando_detalhar = null;

    public bool $oferta_dispensada = false;

    public bool $retomando_rascunho = false;

    public bool $rascunho_expirou = false;

    /** B1: já existe lançamento vigente desta competência. */
    public bool $competencia_ja_lancada = false;

    /**
     * Códigos dos alertas aceitos no bloco 6 — gravados fora do hash.
     *
     * @var list<string>
     */
    public array $alertas_aceitos = [];

    /** Já tentou avançar/fechar e falhou: só então B3/B4 aparecem (B4 mudaria a cada dígito digitado). */
    public bool $tentou = false;

    public ?string $erro_geral = null;

    /** Retificação: a tela corrige a vigente de `competencia_fixa` em vez de lançar um mês novo. */
    #[Locked]
    public bool $retificando = false;

    /** Versão vigente em que o rascunho de retificação foi aberto (concorrência otimista, ADR-007). */
    #[Locked]
    public int $base_versao = 0;

    #[Locked]
    public string $competencia_fixa = '';

    /** Setor gravado na versão vigente: a estrutura de campos da retificação é a do lançamento original. */
    #[Locked]
    public string $setor_fixo = '';

    /**
     * Histórico dos outros meses para A4/A6, lido uma vez por requisição.
     *
     * @var array{campos: array<string, array<string, int|null>>, lancadas: list<string>}|null
     */
    private ?array $historico = null;

    /** A vigente já não é a versão-base: outra sessão corrigiu o mês enquanto este rascunho estava aberto. */
    public bool $conflito = false;

    /** Retificação no-op: nenhum valor mudou em relação à vigente. */
    public bool $sem_mudanca = false;

    public function mount(EmpresaAnalisada $empresa, ?string $competencia = null): void
    {
        $this->empresa = $empresa;

        if ($competencia !== null) {
            $this->abrirRetificacao(Competencia::deTexto($competencia));

            return;
        }

        $this->modos = $this->modosEmTexto(DrmPreferencia::daEmpresa($empresa)->modos());

        $this->competencia = $this->competenciaInicial()->texto();
        $this->carregarCompetencia(preservarDigitado: false);
    }

    // ------------------------------------------------------------------ navegação

    public function proximo(): void
    {
        $this->limparFeedback();

        if (! $this->blocoEstaValido($this->bloco_atual)) {
            return;
        }

        $concluido = $this->bloco_atual;
        $rascunho = $this->irPara($this->bloco_atual + 1);
        $this->evento(fn () => EventosDrm::blocoConcluido($rascunho, $this->empresa, $this->setor(), $concluido));
    }

    public function voltar(): void
    {
        $this->limparFeedback();
        $this->bloco_atual = max(1, $this->bloco_atual - 1);
        $this->dispatch('focar-bloco');
    }

    /** Link "editar" da conferência e `Revisar` dos alertas: volta ao bloco, sem validar o atual. */
    public function irParaBloco(int $bloco): void
    {
        $this->limparFeedback();
        $this->bloco_atual = max(1, min(self::BLOCOS, $bloco));
        $this->dispatch('focar-bloco');
    }

    /** Leva ao campo que causou o erro (bloqueio ou resumo de erros) e pede foco. */
    public function irParaCampo(string $campo): void
    {
        $this->limparFeedback();
        $this->bloco_atual = $campo === 'D00' ? 1 : $this->blocoDoCampo($campo);
        $this->dispatch('focar-campo', campo: $campo);
    }

    /** DDR-003 item 5: conferir tudo vai à conferência final; nunca fecha o mês. */
    public function conferirTudo(): void
    {
        $this->limparFeedback();

        if (! $this->blocoEstaValido(1)) {
            return;
        }

        $this->repetidos = [];
        $rascunho = $this->irPara(self::BLOCOS);
        $this->evento(fn () => EventosDrm::blocoConcluido($rascunho, $this->empresa, $this->setor(), 1)); // só o bloco 1 foi validado.
    }

    public function dispensarOferta(): void
    {
        $this->oferta_dispensada = true;
    }

    /** `Conferi este bloco`: apaga as marcas de herança do bloco (DDR-003 item 2). */
    public function conferirBloco(): void
    {
        $this->repetidos = array_values(array_diff($this->repetidos, $this->camposDoBloco($this->bloco_atual)));
    }

    // ------------------------------------------------------------------ competência

    public function updatedCompetencia(): void
    {
        if ($this->retificando) {
            $this->competencia = $this->competencia_fixa; // a competência retificada não se troca.

            return;
        }

        $this->limparFeedback();
        $this->erro_geral = null;

        if ($this->competenciaValida() === null) {
            $this->competencia = $this->competenciaInicial()->texto();
        }

        $this->carregarCompetencia(preservarDigitado: true);
    }

    // ------------------------------------------------------------------ campos

    /**
     * Editar um campo apaga a marca de herança e o erro dele (DDR-003 item 2). O error
     * bag do Livewire persiste entre requisições, então sem isto a mensagem do último
     * avanço continuaria na tela depois da correção.
     */
    public function updatedValores(mixed $valor, ?string $campo = null): void
    {
        if ($campo === null) {
            return; // o array inteiro foi substituído (primeiro valor de um `valores` vazio): nada a conferir campo a campo.
        }

        $this->resetValidation('valores.'.$campo);
        $this->repetidos = array_values(array_diff($this->repetidos, [$campo]));

        $grupo = Grupo::doCampo($campo);
        if ($grupo !== null && $campo === $grupo->totalizador()) {
            // Total editado à mão: deixa de ser a soma dos itens.
            unset($this->somados[$grupo->value]);
        }

        if ($campo === 'D01' || $campo === 'D02') {
            $this->resetValidation('bloqueio.B4');
        }
    }

    // ------------------------------------------------------------------ modo dos grupos

    /**
     * Troca o modo de um grupo (DDR-002).
     *
     * - detalhado → agregado: não destrutivo. O total vem da soma dos itens e os itens
     *   ficam guardados na sessão.
     * - agregado → detalhado: se o total ainda é a soma automática, é só "voltar atrás"
     *   (restaura os itens). Se foi digitado, é destrutivo e pede confirmação inline.
     */
    public function alternarModo(string $grupo, string $modo): void
    {
        $g = Grupo::tryFrom($grupo);
        $novo = Modo::tryFrom($modo);
        if ($g === null || $novo === null || ($this->modos[$g->value] ?? null) === $novo->value) {
            return;
        }

        $this->confirmando_detalhar = null;

        if ($novo === Modo::Agregado) {
            $soma = $this->somaDosItens($g);
            $this->valores[$g->totalizador()] = Dinheiro::mascara($soma);
            $this->somados[$g->value] = $soma;
            $this->repetidos = array_values(array_diff($this->repetidos, [$g->totalizador()]));
            $this->modos[$g->value] = Modo::Agregado->value;

            return;
        }

        $total = $this->valores[$g->totalizador()] ?? null;
        [, $centavos] = Dinheiro::interpretar($total);

        $aindaESoma = isset($this->somados[$g->value]) && $centavos === $this->somados[$g->value];
        if ($aindaESoma || trim((string) $total) === '') {
            $this->modos[$g->value] = Modo::Detalhado->value;
            $this->valores[$g->totalizador()] = '';
            unset($this->somados[$g->value]);

            return;
        }

        $this->confirmando_detalhar = $g->value;
    }

    public function confirmarDetalhar(): void
    {
        $g = Grupo::tryFrom((string) $this->confirmando_detalhar);
        if ($g === null) {
            return;
        }

        $itens = $g->itens($this->setor());
        foreach ($itens as $item) {
            unset($this->valores[$item]);
        }
        $this->repetidos = array_values(array_diff($this->repetidos, $itens));
        $this->valores[$g->totalizador()] = '';
        $this->modos[$g->value] = Modo::Detalhado->value;
        unset($this->somados[$g->value]);
        $this->confirmando_detalhar = null;
    }

    public function cancelarDetalhar(): void
    {
        $this->confirmando_detalhar = null;
    }

    // ------------------------------------------------------------------ alertas e fechamento

    /** O aceite único do bloco 6: registra os alertas hoje presentes. */
    public function aceitarAlertas(): void
    {
        foreach ($this->alertasAtuais() as $alerta) {
            if (! in_array($alerta->codigo, $this->alertas_aceitos, true)) {
                $this->alertas_aceitos[] = $alerta->codigo;
            }
        }
        $this->salvarRascunho($this->bloco_atual);
    }

    /** Conflito de versão: descarta o rascunho velho e leva à versão atual, de onde se reabre a correção. */
    public function abrirVersaoAtual(): mixed
    {
        abort_unless($this->retificando, 404);
        DrmRascunho::paraCompetencia($this->empresa, $this->competenciaObjeto()->dia01())?->delete();

        return $this->redirect(route('drm.mes', [$this->empresa, $this->competencia_fixa]), navigate: false);
    }

    public function fechar(FecharLancamento $fechar, RetificarLancamento $retificar): mixed
    {
        $this->limparFeedback();
        $this->erro_geral = null;

        // STORY-084: o acesso pode ter acabado com a página aberta; recarregar mostra o bloqueio e o rascunho fica.
        if (! AcessoDrm::permitido((string) Auth::id())) {
            return $this->redirect(request()->header('Referer') ?? route('home'), navigate: false);
        }

        if ($this->competencia_ja_lancada) {
            return null;
        }

        if (! $this->tudoEstaValido()) {
            return null;
        }

        $alertas = $this->alertasAtuais();
        $pendentes = array_filter($alertas, fn (Ocorrencia $a): bool => ! in_array($a->codigo, $this->alertas_aceitos, true));
        if ($pendentes !== []) {
            return null;
        }

        $competencia = $this->competenciaObjeto();

        $rascunho = DrmRascunho::paraCompetencia($this->empresa, $competencia->dia01());

        try {
            if ($this->retificando) {
                $retificar->execute($this->empresa, $competencia, $this->lancamento(), $alertas, $this->base_versao, $rascunho);
            } else {
                $fechar->execute($this->empresa, $competencia, $this->lancamento(), $alertas, $rascunho);
            }
        } catch (RetificacaoConflitante) {
            $this->conflito = true;

            return null;
        } catch (RetificacaoSemMudanca) {
            $this->sem_mudanca = true;

            return null;
        } catch (CompetenciaNaoLancada) {
            abort(404);
        } catch (CompetenciaJaLancada) {
            // B1: alguém (outra aba, duplo clique) lançou antes. O banco decidiu; só oferecemos as saídas.
            $this->competencia_ja_lancada = true;
            $this->bloco_atual = 1;
            $this->eventoBloqueio(new Ocorrencia('B1', 'D00'), 1);

            return null;
        } catch (Throwable $e) {
            $requestId = request()->header('X-Request-Id') ?? (string) Str::uuid();
            Log::error('drm.lancamento.falha_inesperada', [
                'request_id' => $requestId,
                'empresa_id' => $this->empresa->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            $this->erro_geral = 'Algo deu errado do nosso lado. Já fomos notificados. Se continuar, fale com o suporte.';

            return null;
        }

        return $this->redirect(route('drm.mes', [$this->empresa, $competencia->texto()]), navigate: false);
    }

    // ------------------------------------------------------------------ render

    /**
     * Todo campo existe em `valores` (vazio), desde a primeira renderização (STORY-095, D-1).
     *
     * Chave AUSENTE vira, no cliente, uma atualização do array INTEIRO (`valores`), e a resposta
     * a substitui por completo: o Livewire reescreve o <input> com o valor que a requisição levou
     * e apaga as teclas digitadas enquanto ela voava. Com a chave já presente, a atualização é só
     * a da folha (`valores.D01`) e o que foi digitado depois fica. Texto vazio e chave ausente
     * significam o mesmo para o domínio (`Dinheiro::interpretar`), então isto não muda nenhum valor.
     */
    public function dehydrate(): void
    {
        $this->valores = [...array_fill_keys(LancamentoDrm::todosOsCampos(), ''), ...$this->valores];
    }

    public function render(): View
    {
        $conteudo = app(ConteudoDrm::class);
        $setor = $this->setor();
        $competencia = $this->competenciaValida();
        $lancamento = $this->lancamento();

        $bloqueios = $competencia === null ? [] : $this->bloqueiosVisiveis($lancamento, $competencia);
        $bloqueios = array_values(array_filter($bloqueios, fn (Ocorrencia $b): bool => $b->codigo === 'B2' || $this->tentou));
        $alertas = $this->bloco_atual === self::BLOCOS && $competencia !== null ? $this->alertasAtuais() : [];

        return view('livewire.drm.lancamento', [
            'conteudo' => $conteudo,
            'mensagens' => app(MensagensDrm::class),
            'setor' => $setor,
            'nomesDosBlocos' => $conteudo->nomesDosBlocos($setor->value),
            'camposDoBloco' => $this->camposDoBloco($this->bloco_atual),
            'competenciaObj' => $competencia,
            'mesesDisponiveis' => Competencia::ultimosFechados($this->hoje()),
            'lancamento' => $lancamento,
            'bloqueios' => $bloqueios,
            'alertas' => $alertas,
            'alertasPendentes' => array_values(array_filter($alertas, fn (Ocorrencia $a): bool => ! in_array($a->codigo, $this->alertas_aceitos, true))),
            'repetidosDoBloco' => array_values(array_intersect($this->repetidos, $this->camposDoBloco($this->bloco_atual))),
            'origem' => $this->mes_origem === '' ? null : Competencia::deTexto($this->mes_origem),
            'grupos' => Grupo::cases(),
            'existente' => $this->competencia_ja_lancada && $competencia !== null ? $competencia : null,
        ])->layoutData([
            'title' => ($this->retificando ? 'Corrigir ' : 'Lançar ').($competencia?->rotulo() ?? ''),
            'breadcrumb' => [
                ['label' => 'Minhas Empresas', 'url' => route('home')],
                ['label' => $this->empresa->nomeExibicao(), 'url' => route('empresas.show', $this->empresa)],
                ['label' => $this->retificando ? 'Corrigir o mês' : 'Lançar o mês', 'url' => '#'],
            ],
        ]);
    }

    // ------------------------------------------------------------------ rascunho e carga

    private function irPara(int $bloco): DrmRascunho
    {
        $rascunho = $this->salvarRascunho(min(self::BLOCOS, $bloco));
        $this->bloco_atual = min(self::BLOCOS, $bloco);
        $this->retomando_rascunho = false;
        $this->rascunho_expirou = false;
        $this->dispatch('focar-bloco');

        return $rascunho;
    }

    /**
     * Competência inicial: a da URL (`?competencia=`), senão a do rascunho mais recente
     * (retomada em outra sessão), senão o mês anterior ao corrente.
     */
    private function competenciaInicial(): Competencia
    {
        $daUrl = request()->query('competencia');
        if (is_string($daUrl) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $daUrl) === 1) {
            return Competencia::deTexto($daUrl);
        }

        $rascunho = DrmRascunho::maisRecente($this->empresa);

        return $rascunho !== null ? Competencia::deData($rascunho->competencia) : Competencia::padrao($this->hoje());
    }

    /**
     * Posiciona o formulário para a competência escolhida: retoma o rascunho dela, se houver;
     * senão semeia a partir do último lançamento anterior (CA-5).
     *
     * Ao trocar a competência no bloco 1 (`preservarDigitado`), o que o usuário já digitou
     * fica; só os campos em branco ou ainda marcados como herdados recebem a nova semente.
     */
    private function carregarCompetencia(bool $preservarDigitado): void
    {
        $competencia = $this->competenciaValida();
        if ($competencia === null) {
            return;
        }

        $this->competencia_ja_lancada = DrmLancamento::vigenteDaCompetencia($this->empresa, $competencia->dia01()) !== null;
        $this->alertas_aceitos = [];
        $this->confirmando_detalhar = null;

        $rascunho = DrmRascunho::paraCompetencia($this->empresa, $competencia->dia01());
        if ($rascunho !== null) {
            $this->hidratarDoRascunho($rascunho);
            $this->eventoRetomada($rascunho);

            return;
        }

        $this->rascunho_expirou = DrmRascunho::query()
            ->where('empresa_analisada_id', $this->empresa->id)
            ->where('competencia', $competencia->dia01())
            ->where('expires_at', '<=', now())
            ->exists();
        $this->retomando_rascunho = false;

        $this->semear($competencia, $preservarDigitado);
    }

    private function hidratarDoRascunho(DrmRascunho $rascunho): void
    {
        $p = (array) $rascunho->rascunho_payload;

        $this->valores = (array) ($p['valores'] ?? []);
        $this->modos = array_merge($this->modos, (array) ($p['modos'] ?? []));
        $this->repetidos = array_values((array) ($p['repetidos'] ?? []));
        $this->somados = array_map('intval', (array) ($p['somados'] ?? []));
        $this->total_anterior = array_map('intval', (array) ($p['total_anterior'] ?? []));
        $this->mes_origem = (string) ($p['mes_origem'] ?? '');
        $this->alertas_aceitos = array_values((array) ($p['alertas_aceitos'] ?? []));
        $this->oferta_dispensada = (bool) ($p['oferta_dispensada'] ?? false);
        $this->bloco_atual = max(1, min(self::BLOCOS, (int) ($p['bloco'] ?? 1)));
        $this->retomando_rascunho = true;
        $this->rascunho_expirou = false;
    }

    private function semear(Competencia $competencia, bool $preservarDigitado): void
    {
        $anterior = DrmLancamento::anteriorA($this->empresa, $competencia->dia01());

        $semente = $anterior === null
            ? new PreenchimentoInicial
            : PreenchimentoInicial::de($this->setor(), $this->modosObjeto(), (array) $anterior->lancamento_payload);

        $digitados = $preservarDigitado
            ? array_filter(
                $this->valores,
                fn (?string $v, string $campo): bool => trim((string) $v) !== '' && ! in_array($campo, $this->repetidos, true),
                ARRAY_FILTER_USE_BOTH,
            )
            : [];

        $this->valores = [...$semente->valores, ...$digitados];
        $this->repetidos = array_values(array_diff($semente->repetidos, array_keys($digitados)));
        $this->total_anterior = $semente->totalAnterior;
        $this->somados = [];
        foreach ($semente->somados as $grupo) {
            $g = Grupo::from($grupo);
            [, $soma] = Dinheiro::interpretar($this->valores[$g->totalizador()] ?? null);
            $this->somados[$grupo] = (int) $soma;
        }
        $this->mes_origem = $anterior === null ? '' : Competencia::deData($anterior->competencia)->texto();
        $this->bloco_atual = 1;
    }

    /**
     * Reabrir um mês fechado (spec §4.9): 404 se não há vigente; senão retoma o rascunho de retificação ou
     * cria um semeado com a vigente. Abrir e abandonar nunca toca a versão vigente (ADR-007).
     */
    private function abrirRetificacao(Competencia $competencia): void
    {
        $vigente = DrmLancamento::vigenteDaCompetencia($this->empresa, $competencia->dia01());
        abort_if($vigente === null, 404);

        $this->retificando = true;
        $this->competencia = $this->competencia_fixa = $competencia->texto();
        $this->setor_fixo = $vigente->setor;

        $payload = $vigente->lancamento_payload->getArrayCopy();
        $this->modos = $this->modosEmTexto($this->modosDoPayload($payload));

        $rascunho = DrmRascunho::paraCompetencia($this->empresa, $competencia->dia01());
        if ($rascunho !== null && $rascunho->origem === 'retificacao') {
            $this->hidratarDoRascunho($rascunho);
            $this->base_versao = (int) $rascunho->base_versao;
            $this->eventoRetomada($rascunho);

            return;
        }

        $this->valores = PreenchimentoInicial::de($this->setor(), $this->modosObjeto(), $payload)->valores;
        $this->repetidos = []; // são os valores DAQUELE mês, não herdados de outro (spec §4.9 item 3).
        $this->base_versao = $vigente->versao;
        $this->salvarRascunho(1);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, Modo>
     */
    private function modosDoPayload(array $payload): array
    {
        $doPayload = (array) ($payload['modos'] ?? []);
        $modos = [];
        foreach (Grupo::cases() as $grupo) {
            $modos[$grupo->value] = Modo::tryFrom((string) ($doPayload[$grupo->value] ?? '')) ?? Modo::Detalhado;
        }

        return $modos;
    }

    private function salvarRascunho(int $bloco): DrmRascunho
    {
        $competencia = $this->competenciaObjeto();

        return DB::transaction(function () use ($competencia, $bloco): DrmRascunho {
            $rascunho = DrmRascunho::query()->updateOrCreate(
                ['empresa_analisada_id' => $this->empresa->id, 'competencia' => $competencia->dia01()],
                [
                    'usuario_id' => Auth::id(),
                    'origem' => $this->retificando ? 'retificacao' : 'inicial',
                    'base_versao' => $this->retificando ? $this->base_versao : null,
                    'rascunho_payload' => [
                        'valores' => array_filter($this->valores, fn (?string $v): bool => trim((string) $v) !== ''), // vazio = ausente (ver dehydrate).
                        'modos' => $this->modos,
                        'repetidos' => $this->repetidos,
                        'somados' => $this->somados,
                        'total_anterior' => $this->total_anterior,
                        'mes_origem' => $this->mes_origem,
                        'alertas_aceitos' => $this->alertas_aceitos,
                        'oferta_dispensada' => $this->oferta_dispensada,
                        'bloco' => $bloco,
                    ],
                    'expires_at' => now()->addDays(DrmRascunho::TTL_DIAS),
                ],
            );

            // O modo escolhido vale para os próximos meses (Anexo K §K.5) — exceto ao corrigir um mês antigo.
            if (! $this->retificando) {
                DrmPreferencia::gravarModos($this->empresa, $this->modosObjeto());
            }

            // STORY-056 CA-2: a primeira gravação é o lançamento "iniciado" (mesmo critério do `quiz_iniciado`).
            if ($rascunho->wasRecentlyCreated) {
                $this->evento(fn () => EventosDrm::lancamentoIniciado($rascunho, $this->empresa, $this->setor(), $this->mes_origem !== ''));
            }

            return $rascunho;
        });
    }

    // ------------------------------------------------------------------ eventos analíticos (STORY-056)

    /**
     * Só uma ação liberada por {@see AcessoDrm} gera evento de lançamento (STORY-084 CA-7): com o acesso
     * perdido no meio da sessão, a tela pode até continuar aberta, mas nada dela vira dado de adesão.
     */
    private function evento(callable $emitir): void
    {
        if (AcessoDrm::permitido((string) Auth::id())) {
            $emitir();
        }
    }

    /** Voltar a um rascunho depois de uma pausa: o mecanismo que o PDR-003 quer medir (CA-4). */
    private function eventoRetomada(DrmRascunho $rascunho): void
    {
        $pausa = (int) config('drm.eventos.retomada_apos_minutos');
        if ($rascunho->updated_at->diffInMinutes(now(), true) < $pausa) {
            return;
        }

        $this->evento(fn () => EventosDrm::rascunhoRetomado($rascunho, $this->empresa, $this->setor(), (int) (((array) $rascunho->rascunho_payload)['bloco'] ?? 1)));
    }

    /** Regra bloqueante B1–B4 que impediu de avançar (CA-5): o insumo da taxa de atrito por regra. */
    private function eventoBloqueio(Ocorrencia $bloqueio, int $bloco): void
    {
        $this->evento(fn () => EventosDrm::regraBloqueanteAcionada(
            $this->getId(),
            $this->empresa,
            $this->setor(),
            $this->competenciaObjeto()->texto(),
            $this->retificando ? 'retificacao' : 'inicial',
            $bloco,
            $bloqueio->codigo,
            $bloqueio->campo,
        ));
    }

    // ------------------------------------------------------------------ validação

    /**
     * Valida o bloco: erros de campo (obrigatório, formato, B3) e os bloqueios cujo campo
     * pertence a ele (B1 e B2 no bloco 1; B4 em D02). Devolve false e deixa as mensagens no error bag.
     */
    private function blocoEstaValido(int $bloco): bool
    {
        $this->resetValidation();
        $campos = $this->camposDoBloco($bloco);
        $lancamento = $this->lancamento();
        $competencia = $this->competenciaValida();
        $ok = $competencia !== null && $this->errosDeCampo($lancamento, $campos);

        if ($competencia !== null) {
            foreach (ValidaLancamento::bloqueios($lancamento, $competencia, $this->hoje()) as $bloqueio) {
                if ($bloqueio->campo === 'D00' || in_array($bloqueio->campo, $campos, true)) {
                    $ok = false;
                    $this->eventoBloqueio($bloqueio, $bloco);
                }
            }
        }

        if ($bloco === 1 && $this->competencia_ja_lancada) {
            $ok = false;
            $this->eventoBloqueio(new Ocorrencia('B1', 'D00'), 1);
        }

        $this->tentou = ! $ok;

        return $ok;
    }

    /**
     * Valida todos os blocos (fechamento). Erros de campo vão ao error bag; o bloco 6 lista
     * o resumo com link para cada campo (spec §4.4).
     */
    private function tudoEstaValido(): bool
    {
        $this->resetValidation();
        $lancamento = $this->lancamento();
        $competencia = $this->competenciaValida();

        $ok = $this->errosDeCampo($lancamento, array_keys($lancamento->erros));
        $bloqueios = $competencia === null ? [] : ValidaLancamento::bloqueios($lancamento, $competencia, $this->hoje());
        foreach ($bloqueios as $bloqueio) {
            $this->eventoBloqueio($bloqueio, $this->blocoDoCampo((string) $bloqueio->campo));
        }
        $ok = $ok && $competencia !== null && $bloqueios === [];

        $this->tentou = ! $ok;

        return $ok;
    }

    /**
     * Põe no error bag os erros dos `$campos`. B3 usa o texto do pacote de conteúdo.
     *
     * @param  list<string>  $campos
     */
    private function errosDeCampo(LancamentoDrm $lancamento, array $campos): bool
    {
        $ok = true;
        foreach ($lancamento->erros as $campo => $erro) {
            if (! in_array($campo, $campos, true)) {
                continue;
            }
            $this->addError('valores.'.$campo, $this->mensagemDeCampo($campo, $erro));
            $ok = false;
        }

        return $ok;
    }

    /**
     * Bloqueios B2, B3 e B4 do estado atual. B3 também aparece como mensagem do campo.
     *
     * @return list<Ocorrencia>
     */
    private function bloqueiosVisiveis(LancamentoDrm $lancamento, Competencia $competencia): array
    {
        return ValidaLancamento::bloqueios($lancamento, $competencia, $this->hoje());
    }

    /**
     * @return list<Ocorrencia>
     */
    private function alertasAtuais(): array
    {
        $competencia = $this->competenciaValida();
        $lancamento = $this->lancamento();
        if ($competencia === null || $lancamento->temErros()) {
            return [];
        }

        // A4 e A6 olham os outros meses; na retificação o mês já existe, então A6 (salto) não se aplica.
        $historico = $this->historico ??= HistoricoDrm::paraAlertas($this->empresa);

        return AvaliadorDeAlertas::comHistorico($historico['campos'], $this->retificando ? [] : $historico['lancadas'])
            ->avaliar($lancamento, $competencia);
    }

    private function mensagemDeCampo(string $campo, string $erro): string
    {
        $rotulo = (string) app(ConteudoDrm::class)->rotuloCampo($campo);

        return match ($erro) {
            LancamentoDrm::ERRO_OBRIGATORIO => "Informe o valor de {$rotulo}. Se não houve, informe 0,00.",
            Dinheiro::NEGATIVO => app(MensagensDrm::class)->de(new Ocorrencia('B3', $campo))['texto'],
            default => 'Informe um valor numérico válido (ex.: 1.234,56).',
        };
    }

    private function limparFeedback(): void
    {
        $this->confirmando_detalhar = null;
        $this->tentou = false;
        $this->sem_mudanca = false;
    }

    // ------------------------------------------------------------------ derivados

    private function lancamento(): LancamentoDrm
    {
        return LancamentoDrm::deTexto($this->setor(), $this->modosObjeto(), $this->valores);
    }

    private function setor(): Setor
    {
        return Setor::tryFrom($this->setor_fixo) ?? Setor::doCnae($this->empresa->cnae);
    }

    private function hoje(): CarbonImmutable
    {
        return DataExibicao::agora();
    }

    private function competenciaValida(): ?Competencia
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->competencia) === 1
            ? Competencia::deTexto($this->competencia)
            : null;
    }

    private function competenciaObjeto(): Competencia
    {
        return $this->competenciaValida() ?? Competencia::padrao($this->hoje());
    }

    /**
     * @return array<string, Modo>
     */
    private function modosObjeto(): array
    {
        $modos = [];
        foreach (Grupo::cases() as $grupo) {
            $modos[$grupo->value] = Modo::tryFrom($this->modos[$grupo->value] ?? '') ?? Modo::Detalhado;
        }

        return $modos;
    }

    /**
     * @param  array<string, Modo>  $modos
     * @return array<string, string>
     */
    private function modosEmTexto(array $modos): array
    {
        return array_map(static fn (Modo $m): string => $m->value, $modos);
    }

    private function somaDosItens(Grupo $grupo): int
    {
        $soma = 0;
        foreach ($grupo->itens($this->setor()) as $item) {
            [, $centavos] = Dinheiro::interpretar($this->valores[$item] ?? null);
            $soma += (int) $centavos;
        }

        return $soma;
    }

    /**
     * Campos do bloco na ordem em que aparecem na tela.
     *
     * @return list<string>
     */
    private function camposDoBloco(int $bloco): array
    {
        $setor = $this->setor();
        $modos = $this->modosObjeto();

        return match ($bloco) {
            1 => ['D01', 'D02'],
            2 => LancamentoDrm::camposDoGrupo(Grupo::Custo, $setor, $modos),
            3 => LancamentoDrm::camposDoGrupo(Grupo::Vendas, $setor, $modos),
            4 => LancamentoDrm::camposDoGrupo(Grupo::Administrativas, $setor, $modos),
            5 => ['D40', ...LancamentoDrm::camposDoGrupo(Grupo::Financeiras, $setor, $modos), 'D41', 'D42'],
            default => [],
        };
    }

    private function blocoDoCampo(string $campo): int
    {
        foreach ([1, 2, 3, 4, 5] as $bloco) {
            if (in_array($campo, $this->camposDoBloco($bloco), true)) {
                return $bloco;
            }
        }

        // Campo de grupo agregado visto do outro modo, ou desconhecido: o bloco do grupo.
        $grupo = Grupo::doCampo($campo);

        return match ($grupo) {
            Grupo::Custo => 2,
            Grupo::Vendas => 3,
            Grupo::Administrativas => 4,
            Grupo::Financeiras => 5,
            default => 1,
        };
    }
}
