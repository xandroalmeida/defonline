<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Domain\Cobranca\Arrependimento;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ResultadoReembolso;
use App\Domain\Cobranca\StatusAssinatura;
use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\Assinatura;
use App\Models\ConsumoAnalise;
use App\Models\Cupom;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Services\Cobranca\MarcarNotaFiscal;
use App\Services\Cobranca\PreviaDoReembolso;
use App\Services\Cobranca\ReembolsarCobranca;
use App\Services\Cobranca\ReembolsoNaoRealizado;
use App\Support\DataExibicao;
use App\Support\PermissaoAdmin;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * STORY-085 / PDR-020 / espec §2.4 — operação financeira no backoffice: assinaturas e cobranças com filtros,
 * reembolso (total, com motivo e confirmação), pagamentos que precisam ser devolvidos, contestações e o
 * controle de nota fiscal (a emissão é fora do código no MVP).
 *
 * Permissão `financeiro.gerenciar` em toda requisição ({@see ExigePermissao}); durante o "Logar como" o
 * backoffice inteiro é 404, então nenhuma ação daqui roda numa sessão personificada (CA-8).
 */
#[Layout('components.layouts.app')]
final class Financeiro extends Component
{
    use ExigePermissao;
    use WithPagination;

    public const POR_PAGINA = 15;

    public const MOTIVO_MIN = 5;

    public const MOTIVO_MAX = 500;

    public const LISTA_ATENCAO = 50;

    public string $busca = '';

    public string $statusAssinatura = '';

    public string $statusCobranca = '';

    public string $de = '';

    public string $ate = '';

    /** Cobrança aberta no painel de reembolso. */
    public ?string $reembolsoId = null;

    public string $motivo = '';

    /** Cobrança aberta no painel de nota fiscal. */
    public ?string $notaId = null;

    public string $notaNumero = '';

    public ?string $mensagem = null;

    public ?string $erro = null;

    public function mount(): void
    {
        $this->auditar('backoffice.financeiro_listado', 'Invoice');
    }

    public function filtrar(): void
    {
        $this->validate([
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
            'statusAssinatura' => ['nullable', 'in:'.implode(',', array_column(StatusAssinatura::cases(), 'value'))],
            'statusCobranca' => ['nullable', 'in:'.implode(',', array_column(EstadoCobranca::cases(), 'value'))],
        ], [
            'de.date_format' => 'Informe a data inicial no formato dia/mês/ano.',
            'ate.date_format' => 'Informe a data final no formato dia/mês/ano.',
            'ate.after_or_equal' => 'A data final não pode ser anterior à inicial.',
        ]);

        $this->busca = trim($this->busca);
        $this->resetPage('assinaturas');
        $this->resetPage('cobrancas');
    }

    public function limparFiltros(): void
    {
        $this->resetValidation();
        $this->reset(['busca', 'statusAssinatura', 'statusCobranca', 'de', 'ate']);
        $this->resetPage('assinaturas');
        $this->resetPage('cobrancas');
    }

    public function abrirReembolso(string $id): void
    {
        $this->resetValidation();
        $this->limparAvisos();
        $this->notaId = null;
        $invoice = Invoice::query()->findOrFail($id);

        // Contratação sem cobrança (valor zero): não há dinheiro a devolver, então o painel nem abre.
        if ($invoice->valor_centavos === 0) {
            $this->erro = 'Esta contratação foi feita sem cobrança (valor zero): não há o que reembolsar.';

            return;
        }

        $this->reembolsoId = $invoice->id;
        $this->motivo = '';
    }

    public function cancelarReembolso(): void
    {
        $this->reembolsoId = null;
        $this->motivo = '';
        $this->resetValidation();
    }

    public function confirmarReembolso(ReembolsarCobranca $reembolsos): void
    {
        $invoice = Invoice::query()->findOrFail($this->reembolsoId);
        $this->motivo = trim($this->motivo);

        $this->validate([
            'motivo' => ['required', 'string', 'min:'.self::MOTIVO_MIN, 'max:'.self::MOTIVO_MAX],
        ], [
            'motivo.required' => 'Informe o motivo do reembolso.',
            'motivo.min' => 'Descreva o motivo com pelo menos '.self::MOTIVO_MIN.' caracteres.',
            'motivo.max' => 'O motivo pode ter até '.self::MOTIVO_MAX.' caracteres.',
        ]);

        $this->limparAvisos();
        $admin = Auth::user();
        assert($admin instanceof Usuario);

        try {
            $resultado = $reembolsos->solicitar($invoice, $admin, $this->motivo);
        } catch (ReembolsoNaoRealizado $e) {
            $this->erro = $e->getMessage();

            return;
        } catch (ProvedorIndisponivel) {
            $this->erro = 'O provedor de pagamento não respondeu. O resultado do estorno é desconhecido: confira o estado da cobrança no painel do provedor antes de tentar de novo.';

            return;
        }

        $this->mensagem = match ($resultado) {
            ResultadoReembolso::Concluido => 'Reembolso confirmado pelo provedor. A cobrança está estornada e o que ela concedeu foi retirado.',
            ResultadoReembolso::AguardandoAutorizacao => 'Reembolso pedido. Para concluir, autorize o estorno no painel do provedor de pagamento (ações críticas): a cobrança vira "Estornada" sozinha quando o provedor avisar.',
            default => 'Reembolso aceito pelo provedor e em andamento: a cobrança vira "Estornada" quando ele confirmar.',
        };
        $this->cancelarReembolso();
    }

    public function abrirNota(string $id): void
    {
        $this->resetValidation();
        $this->limparAvisos();
        $this->reembolsoId = null;
        $this->notaId = Invoice::query()->findOrFail($id)->id;
        $this->notaNumero = '';
    }

    public function cancelarNota(): void
    {
        $this->notaId = null;
        $this->notaNumero = '';
        $this->resetValidation();
    }

    public function confirmarNota(MarcarNotaFiscal $notas): void
    {
        $invoice = Invoice::query()->findOrFail($this->notaId);
        $this->notaNumero = trim($this->notaNumero);

        $this->validate([
            'notaNumero' => ['nullable', 'string', 'max:'.MarcarNotaFiscal::NUMERO_MAX],
        ], [
            'notaNumero.max' => 'O número da nota pode ter até '.MarcarNotaFiscal::NUMERO_MAX.' caracteres.',
        ]);

        $this->limparAvisos();
        $admin = Auth::user();
        assert($admin instanceof Usuario);

        try {
            $notas->marcar($invoice, $admin, $this->notaNumero === '' ? null : $this->notaNumero);
        } catch (ReembolsoNaoRealizado $e) {
            $this->erro = $e->getMessage();
            $this->cancelarNota();

            return;
        }

        $this->mensagem = 'Nota fiscal marcada como emitida.';
        $this->cancelarNota();
    }

    public function render(): View
    {
        $reembolsoAberto = $this->reembolsoId === null ? null : Invoice::query()->with('usuario')->find($this->reembolsoId);
        $cobrancas = $this->cobrancas();

        return view('livewire.backoffice.financeiro', [
            'statusAssinaturas' => StatusAssinatura::cases(),
            'statusCobrancas' => EstadoCobranca::cases(),
            'assinaturas' => $this->assinaturas(),
            'cobrancas' => $cobrancas,
            'sinais' => $this->sinaisDeArrependimento($cobrancas->getCollection()),
            'devolver' => $this->devolver(),
            // STORY-090 CA-2: renovações cobradas por valor diferente do contratado.
            'divergentes' => Invoice::query()->with('usuario')->whereNotNull('valor_esperado_centavos')->orderByDesc('pago_em')->limit(self::LISTA_ATENCAO)->get(),
            'contestadas' => Invoice::query()->with('usuario')->where('status', EstadoCobranca::Contestada->value)->latest('pago_em')->limit(self::LISTA_ATENCAO)->get(),
            'semNota' => Invoice::query()->with('usuario')->where('status', EstadoCobranca::Paga->value)
                ->where('valor_centavos', '>', 0)->whereNull('nf_emitida_em')->whereNull('devolucao_motivo')->whereNull('reembolso_estado')
                ->orderBy('pago_em')->limit(self::LISTA_ATENCAO)->get(),
            'reembolsoAberto' => $reembolsoAberto,
            'cupomAberto' => $reembolsoAberto?->cupom_id === null ? null : Cupom::query()->find($reembolsoAberto->cupom_id),
            'previa' => $reembolsoAberto === null ? null : $this->previaDe($reembolsoAberto),
            'notaAberta' => $this->notaId === null ? null : Invoice::query()->with('usuario')->find($this->notaId),
        ])->layoutData(['title' => 'Financeiro']);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::GerenciarFinanceiro;
    }

    private function previaDe(Invoice $invoice): PreviaDoReembolso
    {
        return app(ReembolsarCobranca::class)->previa($invoice);
    }

    /** @return LengthAwarePaginator<int, Assinatura> */
    private function assinaturas(): LengthAwarePaginator
    {
        return Assinatura::query()
            ->with(['usuario', 'plano'])
            ->when($this->busca !== '', fn (Builder $q) => $q->whereIn('usuario_id', $this->usuariosDaBusca()))
            ->when($this->statusAssinatura !== '', fn (Builder $q) => $q->where('status', $this->statusAssinatura))
            ->when($this->de !== '', fn (Builder $q) => $q->where('data_inicio', '>=', $this->inicioDoDia($this->de)))
            ->when($this->ate !== '', fn (Builder $q) => $q->where('data_inicio', '<', $this->inicioDoDia($this->ate)->addDay()))
            ->orderByDesc('data_inicio')
            ->paginate(self::POR_PAGINA, pageName: 'assinaturas');
    }

    /** @return LengthAwarePaginator<int, Invoice> */
    private function cobrancas(): LengthAwarePaginator
    {
        $quando = 'coalesce(pago_em, created_at)';

        return Invoice::query()
            ->with('usuario')
            ->when($this->busca !== '', fn (Builder $q) => $q->whereIn('usuario_id', $this->usuariosDaBusca()))
            ->when($this->statusCobranca !== '', fn (Builder $q) => $q->where('status', $this->statusCobranca))
            ->when($this->de !== '', fn (Builder $q) => $q->whereRaw("{$quando} >= ?", [$this->inicioDoDia($this->de)->format('Y-m-d H:i:sP')]))
            ->when($this->ate !== '', fn (Builder $q) => $q->whereRaw("{$quando} < ?", [$this->inicioDoDia($this->ate)->addDay()->format('Y-m-d H:i:sP')]))
            ->orderByRaw("{$quando} desc")
            ->paginate(self::POR_PAGINA, pageName: 'cobrancas');
    }

    /**
     * Pagamentos que entraram sem conceder nada e ainda não foram devolvidos.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Invoice>
     */
    private function devolver(): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::query()->with('usuario')
            ->whereNotNull('devolucao_motivo')
            ->where('status', EstadoCobranca::Paga->value)
            ->orderByDesc('pago_em')
            ->limit(self::LISTA_ATENCAO)
            ->get();
    }

    /** @return \Illuminate\Database\Query\Builder */
    private function usuariosDaBusca()
    {
        $padrao = '%'.addcslashes($this->busca, '%_\\').'%';

        return Usuario::withTrashed()
            ->where(fn (Builder $q) => $q->where('nome', 'ilike', $padrao)->orWhere('email', 'ilike', $padrao))
            ->select('id')
            ->toBase();
    }

    private function inicioDoDia(string $data): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $data, DataExibicao::fuso())->startOfDay();
    }

    /**
     * CA-3 — para cada cobrança paga da página: ainda está nos 7 dias corridos e houve análise desde a contratação?
     * Duas consultas para a página inteira.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return array<string, array{no_prazo: bool, dias: int|null, fez_analise: bool}>
     */
    private function sinaisDeArrependimento($invoices): array
    {
        $pagas = $invoices->filter(fn (Invoice $i): bool => $i->pago_em !== null && $i->devolucao_motivo === null);

        $porAssinatura = ConsumoAnalise::query()
            ->whereIn('assinatura_id', $pagas->pluck('assinatura_id')->filter()->unique()->values())
            ->get(['assinatura_id', 'consumido_em']);
        $lotes = LoteCredito::query()->whereIn('invoice_id', $pagas->where('tipo', 'credito')->pluck('id'))->get(['id', 'invoice_id']);
        $porLote = ConsumoAnalise::query()->whereIn('lote_credito_id', $lotes->pluck('id'))->get(['lote_credito_id', 'consumido_em']);

        $agora = CarbonImmutable::now();
        $sinais = [];

        foreach ($pagas as $i) {
            $contratadaEm = CarbonImmutable::instance($i->pago_em);
            $fez = $i->tipo === 'credito'
                ? $porLote->contains(fn (ConsumoAnalise $c): bool => $c->lote_credito_id === $lotes->firstWhere('invoice_id', $i->id)?->id)
                : $porAssinatura->contains(fn (ConsumoAnalise $c): bool => $c->assinatura_id === $i->assinatura_id && $c->consumido_em >= $contratadaEm);

            $sinais[$i->id] = [
                'no_prazo' => Arrependimento::noPrazo($contratadaEm, $agora),
                'dias' => Arrependimento::noPrazo($contratadaEm, $agora) ? (int) $contratadaEm->diffInDays($agora, true) : null,
                'fez_analise' => $fez,
            ];
        }

        return $sinais;
    }

    private function limparAvisos(): void
    {
        $this->mensagem = null;
        $this->erro = null;
    }

    /**
     * @param  array<string, mixed>|null  $depois
     */
    private function auditar(string $action, string $tipo, ?string $subjectId = null, ?array $depois = null): void
    {
        $atorId = (string) Auth::id();

        AuditLogger::log(
            action: $action,
            subjectType: $tipo,
            subjectId: $subjectId,
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
            after: $depois,
        );
    }
}
