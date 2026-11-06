<?php

declare(strict_types=1);

namespace App\Livewire\Cobranca;

use App\Domain\Cobranca\Arrependimento;
use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\LoteVisto;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\RenovacaoPix;
use App\Domain\Cobranca\SituacaoDoPlano;
use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\ConsumoAnalise;
use App\Models\Cupom;
use App\Models\Invoice;
use App\Models\LoteCredito;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\TrocaDeCartao;
use App\Models\Usuario;
use App\Services\Cobranca\AvisarFimDoDesconto;
use App\Services\Cobranca\GerirRenovacao;
use App\Services\Cobranca\GestaoNegada;
use App\Services\Cobranca\TrocaNegada;
use App\Services\Cobranca\TrocarCartao;
use App\Services\Cobranca\TrocarDePlano;
use App\Support\Cobranca\HistoricoDeCobrancas;
use App\Support\Cobranca\RotasCobranca;
use App\Support\DataExibicao;
use App\Support\Personificacao;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * STORY-081 — Meu plano: o que tenho, quanto me resta, quando renova, quanto paguei, e como troco o
 * cartão ou cancelo a renovação (spec STORY-070-meu-plano, DDR-009). Com a cobrança desligada a rota
 * não existe (404). No "Logar como" a tela aparece inteira, mas sem nenhuma ação (CA-8).
 *
 * A tela só LÊ a assinatura que a carteira e os webhooks mantêm: não tem máquina de estados própria.
 */
#[Layout('components.layouts.app')]
final class MeuPlano extends Component
{
    /** `retorno` quando o usuário volta do checkout do novo cartão: a tela passa a esperar a confirmação. */
    #[Url(as: 'cartao')]
    public ?string $retornoCartao = null;

    public bool $trocandoCartao = false;

    /** Resultado da última ação, para `aria-live` (`ok` ou `erro`). */
    public ?string $mensagem = null;

    public string $tipoMensagem = 'ok';

    public function mount(): void
    {
        abort_unless(CobrancaConfig::ligada(), 404);
    }

    public function abrirTrocaDeCartao(): void
    {
        $this->trocandoCartao = true;
        $this->mensagem = null;
    }

    public function fecharTrocaDeCartao(): void
    {
        $this->trocandoCartao = false;
    }

    public function cancelarRenovacao(GerirRenovacao $gerir): void
    {
        $usuario = Auth::user();
        $emAtraso = $usuario instanceof Usuario && Assinatura::query()
            ->where('usuario_id', $usuario->id)->where('status', StatusAssinatura::EmAtraso->value)->exists();

        $this->executar(
            fn (Usuario $u): Assinatura => $gerir->cancelar($u),
            // Em atraso o ciclo não foi pago: o cancelamento encerra o acesso na hora (decisão do PO).
            fn (Assinatura $a): string => $a->status === StatusAssinatura::Expirada
                ? 'Renovação cancelada. Como o ciclo de '.self::data($a->proximo_vencimento).' não foi pago, o acesso foi encerrado agora e nada mais será cobrado. Seu histórico continua disponível.'
                : 'Renovação cancelada. Seu acesso continua até '.self::data($a->proximo_vencimento).'.',
            $emAtraso
                ? 'Não conseguimos cancelar agora. Nada foi alterado: a renovação continua em atraso. Tente de novo.'
                : 'Não conseguimos cancelar agora. Sua renovação continua ativa. Tente de novo.',
        );
    }

    public function reativarRenovacao(GerirRenovacao $gerir): void
    {
        $this->executar(
            fn (Usuario $u): Assinatura => $gerir->reativar($u),
            fn (Assinatura $a): string => 'Renovação reativada. Próxima cobrança em '.self::data($a->proximo_vencimento).'.',
            'Não conseguimos reativar agora. Sua renovação continua cancelada. Tente de novo.',
        );
    }

    /** STORY-082 CA-4 — desfaz o downgrade agendado, enquanto não valeu. */
    public function desfazerTroca(TrocarDePlano $trocas): void
    {
        $usuario = Auth::user();
        $this->mensagem = null;

        if (! $usuario instanceof Usuario) {
            return;
        }

        try {
            $trocas->desfazer($usuario);
            $this->avisar('Troca desfeita. Você continua no plano atual.', 'ok');
        } catch (TrocaNegada $e) {
            $this->avisar($e->motivo === TrocaNegada::ADMIN_PERSONIFICANDO
                ? 'Você está acessando como administrador. Aqui é só leitura.'
                : 'Não foi possível desfazer a troca agora. Atualize a página.', 'erro');
        } catch (ProvedorIndisponivel) {
            $this->avisar('Não conseguimos desfazer a troca agora. A troca continua agendada. Tente de novo.', 'erro');
        }
    }

    public function trocarCartao(TrocarCartao $trocar): mixed
    {
        $usuario = Auth::user();
        $this->mensagem = null;

        if (! $usuario instanceof Usuario) {
            return null;
        }

        try {
            $troca = $trocar->iniciar($usuario);
        } catch (GestaoNegada $e) {
            $this->avisar($this->textoDaNegativa($e), 'erro');

            return null;
        } catch (ProvedorIndisponivel) {
            $this->avisar('Não conseguimos falar com o processador de pagamento agora. O cartão atual continua valendo. Tente de novo.', 'erro');

            return null;
        }

        return $this->redirect((string) $troca->checkout_url);
    }

    public function recarregar(): void
    {
        // Só provoca a nova renderização (botão "Tentar de novo" das cobranças).
    }

    public function render(): View
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof Usuario, 403);

        $agora = CarbonImmutable::now();
        $assinatura = Assinatura::query()
            ->where('usuario_id', $usuario->id)
            ->orderByDesc('data_inicio')
            ->orderByDesc('created_at')
            ->first();
        $vencimento = $assinatura === null ? null : CarbonImmutable::instance($assinatura->proximo_vencimento);
        $situacao = SituacaoDoPlano::de($assinatura?->status, $assinatura->trial ?? false, $vencimento, $agora);
        $limiar = (int) config('services.payments.lote_perto_de_vencer_dias', 30);
        $lotes = $this->lotes($usuario->id, $agora, $limiar);
        $troca = $this->trocaRecente($assinatura);
        $personificando = Personificacao::ativa() !== null;
        $paga = $assinatura !== null && ! $assinatura->trial;
        $renovaSozinha = $paga && $assinatura->id_externo !== null;
        // Plano anual pago com Pix (STORY-080): não renova sozinho; a renovação é um novo QR Code pago por ele.
        $pix = $paga && $assinatura->pagoComPix();

        [$cobrancas, $cobrancasFalharam] = $this->cobrancas($usuario->id);
        $desconto = $this->desconto($assinatura, $agora);

        return view('livewire.cobranca.meu-plano', [
            'assinatura' => $assinatura,
            'situacao' => $situacao,
            'vencimento' => $vencimento,
            'plano' => $assinatura?->plano_id === null ? null : Plano::query()->find($assinatura->plano_id),
            'paga' => $paga,
            'renovaSozinha' => $renovaSozinha,
            'pix' => $pix,
            'janelaPix' => $pix && $vencimento !== null && RenovacaoPix::janelaAberta($vencimento, $agora),
            'urlRenovar' => $pix ? RotasCobranca::url(RotasCobranca::RENOVAR) : null,
            'lotes' => $lotes,
            'creditosTotal' => array_sum(array_column($lotes, 'restante')),
            'temLotePerto' => in_array(true, array_column($lotes, 'perto'), true),
            'desconto' => $desconto,
            'semCartaoSemDesconto' => $this->semCartaoSemDesconto($assinatura, $desconto),
            'cobrancas' => $cobrancas,
            'cobrancasFalharam' => $cobrancasFalharam,
            'personificando' => $personificando,
            'troca' => $troca,
            'cartao' => $this->cartao($assinatura),
            'renovaCotaEm' => $assinatura === null ? null : CarbonImmutable::instance($assinatura->proxima_cota_em ?? $assinatura->proximo_vencimento),
            'arrependimentoDias' => $this->arrependimento($assinatura, $situacao, $agora),
            'arrependimentoCreditosDias' => $this->arrependimentoDeCreditos($usuario->id, $agora),
            'canalArrependimento' => Arrependimento::CANAL,
            'planos' => $situacao === SituacaoDoPlano::SemPlano && $lotes === [] ? Plano::query()->where('ativo', true)->orderBy('ordem')->get() : collect(),
            'pacotes' => $situacao === SituacaoDoPlano::SemPlano && $lotes === [] ? PacoteCredito::query()->where('ativo', true)->orderBy('ordem')->get() : collect(),
            'trialElegivel' => $situacao === SituacaoDoPlano::SemPlano && $lotes === [] && app(Carteira::class)->trialElegivel($usuario->id),
            'urlTeste' => RotasCobranca::url(RotasCobranca::TESTE),
            'urlPlanos' => Route::has('planos') ? route('planos') : null,
            'urlComprar' => RotasCobranca::url(RotasCobranca::COMPRAR),
            'trocaAgendada' => $assinatura === null ? null : TrocarDePlano::agendada($assinatura),
            'urlTrocarPlano' => Route::has(RotasCobranca::TROCAR) ? route('planos') : null,
        ])->layoutData(['title' => 'Meu plano']);
    }

    /**
     * @param  callable(Usuario): Assinatura  $acao
     * @param  callable(Assinatura): string  $sucesso
     */
    private function executar(callable $acao, callable $sucesso, string $falha): void
    {
        $usuario = Auth::user();

        if (! $usuario instanceof Usuario) {
            return;
        }

        try {
            $this->avisar($sucesso($acao($usuario)), 'ok');
        } catch (GestaoNegada $e) {
            $this->avisar($this->textoDaNegativa($e), 'erro');
        } catch (ProvedorIndisponivel) {
            // Diz o estado real: a carteira não mudou.
            $this->avisar($falha, 'erro');
        }
    }

    private function avisar(string $texto, string $tipo): void
    {
        $this->mensagem = $texto;
        $this->tipoMensagem = $tipo;
    }

    private function textoDaNegativa(GestaoNegada $e): string
    {
        return match ($e->motivo) {
            GestaoNegada::ADMIN_PERSONIFICANDO => 'Você está acessando como administrador. Aqui é só leitura.',
            GestaoNegada::NAO_RENOVA_SOZINHA => 'Este plano não renova sozinho, então não há renovação nem cartão para alterar.',
            GestaoNegada::JA_ENCERRADA => 'O ciclo deste plano já terminou, então a renovação não pode mais ser alterada. Atualize a página.',
            default => 'Não encontramos um plano para alterar. Atualize a página.',
        };
    }

    /** @return list<array{restante: int, expira: CarbonImmutable, perto: bool}> */
    private function lotes(string $usuarioId, CarbonImmutable $agora, int $limiar): array
    {
        return LoteCredito::query()
            ->where('usuario_id', $usuarioId)
            ->disponiveis($agora)
            ->orderBy('data_expiracao')
            ->get()
            ->map(function (LoteCredito $l) use ($agora, $limiar): array {
                $visto = new LoteVisto($l->id, $l->status, $l->quantidade_restante, CarbonImmutable::instance($l->data_compra), CarbonImmutable::instance($l->data_expiracao));

                return ['restante' => $l->quantidade_restante, 'expira' => $visto->expiraEm, 'perto' => $visto->pertoDeVencer($agora, $limiar)];
            })
            ->all();
    }

    /**
     * As cobranças podem falhar sozinhas (spec §4.11): plano e análises aparecem do mesmo jeito.
     *
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private function cobrancas(string $usuarioId): array
    {
        try {
            return [app(HistoricoDeCobrancas::class)->do($usuarioId), false];
        } catch (Throwable) {
            return [[], true];
        }
    }

    private function trocaRecente(?Assinatura $assinatura): ?TrocaDeCartao
    {
        if ($assinatura === null || $this->retornoCartao === null) {
            return null;
        }

        return TrocaDeCartao::query()->where('assinatura_id', $assinatura->id)->latest('created_at')->first();
    }

    /**
     * STORY-083 CA-7, CA-8 — o desconto de cupom da assinatura para a tela: quanto, até quando, e o que acontece depois.
     *
     * @return array{cupom: string|null, descricao: string, cheio: int, fim: CarbonImmutable|null, perto: bool, semCartao: bool, gratis: bool, restantes: int|null, urlRecontratar: string|null}|null
     */
    private function desconto(?Assinatura $assinatura, CarbonImmutable $agora): ?array
    {
        $d = $assinatura?->desconto();

        if ($assinatura === null || $d === null || $assinatura->trial) {
            return null;
        }

        $ciclo = CicloCobranca::from((string) $assinatura->ciclo);
        $proximo = $assinatura->periodos_pagos + 1;
        $restantes = $d->periodos === null ? null : max(0, $d->periodos - $assinatura->periodos_pagos);
        $semCartao = $assinatura->id_externo === null && ! $assinatura->pagoComPix();
        $encerrada = $assinatura->status === StatusAssinatura::Expirada;
        $fim = $d->fim($ciclo, $assinatura->data_inicio);

        return [
            'cupom' => $assinatura->cupom_id === null ? null : Cupom::query()->whereKey($assinatura->cupom_id)->value('codigo'),
            'descricao' => $d->valorDoPeriodo(1) === 0 ? 'Sem cobrança' : 'R$ '.number_format($d->desconto / 100, 2, ',', '.').' de desconto por cobrança',
            'cheio' => $d->cheio,
            'fim' => $fim,
            'perto' => $d->pertoDoFim($ciclo, $assinatura->data_inicio, $agora, AvisarFimDoDesconto::DIAS) && ! $encerrada,
            'semCartao' => $semCartao,
            'gratis' => $d->gratuito($proximo),
            'restantes' => $restantes,
            'urlRecontratar' => $encerrada && $semCartao && $assinatura->plano_id !== null
                ? RotasCobranca::url(RotasCobranca::ASSINAR, ['plano' => $assinatura->plano_id, 'ciclo' => $ciclo->value])
                : null,
        ];
    }

    /**
     * STORY-090 CA-3 — plano que veio de um cupom gratuito e perdeu o desconto numa troca de plano: não há cartão
     * no provedor, então o ciclo pago é o último. Sem isto a tela não diria que o plano termina nem ofereceria o cartão.
     *
     * @param  array<string, mixed>|null  $desconto
     * @return array{fim: CarbonImmutable, urlRecontratar: string|null}|null
     */
    private function semCartaoSemDesconto(?Assinatura $assinatura, ?array $desconto): ?array
    {
        if ($assinatura === null || $assinatura->trial || $desconto !== null || $assinatura->id_externo !== null || ! $assinatura->semMeioDePagamento()) {
            return null;
        }

        $encerrada = $assinatura->status === StatusAssinatura::Expirada;

        if (! $encerrada && $assinatura->status !== StatusAssinatura::Ativa) {
            return null;
        }

        return [
            'fim' => CarbonImmutable::instance($assinatura->proximo_vencimento),
            'urlRecontratar' => $encerrada && $assinatura->plano_id !== null
                ? RotasCobranca::url(RotasCobranca::ASSINAR, ['plano' => $assinatura->plano_id, 'ciclo' => $assinatura->ciclo])
                : null,
        ];
    }

    private function cartao(?Assinatura $assinatura): ?string
    {
        if ($assinatura === null || $assinatura->cartao_ultimos4 === null) {
            return null;
        }

        $bandeira = $assinatura->cartao_bandeira === null ? '' : ucfirst(strtolower($assinatura->cartao_bandeira)).' ';

        return "{$bandeira}final {$assinatura->cartao_ultimos4}";
    }

    private function arrependimento(?Assinatura $assinatura, SituacaoDoPlano $situacao, CarbonImmutable $agora): ?int
    {
        if ($assinatura === null || $assinatura->trial || $situacao !== SituacaoDoPlano::Ativo || $assinatura->status !== StatusAssinatura::Ativa) {
            return null;
        }

        return Arrependimento::diasDesde(
            CarbonImmutable::instance($assinatura->data_inicio),
            $agora,
            ConsumoAnalise::query()->where('assinatura_id', $assinatura->id)->exists(),
        );
    }

    /**
     * STORY-094 CA-4 (Termo, cl. 9) — quem comprou créditos também tem o arrependimento: a compra paga mais recente,
     * ainda nos 7 dias, sem reembolso em curso e sem nenhuma análise feita com os créditos dela. Devolve há quantos
     * dias foi paga (0 = hoje), ou `null` se não há nenhuma nessa situação.
     */
    private function arrependimentoDeCreditos(string $usuarioId, CarbonImmutable $agora): ?int
    {
        $compras = Invoice::query()
            ->where('usuario_id', $usuarioId)
            ->where('tipo', 'credito')
            ->where('status', EstadoCobranca::Paga->value)
            ->whereNotNull('pago_em')
            ->where('valor_centavos', '>', 0)
            ->whereNull('devolucao_motivo')
            ->whereNull('reembolso_estado')
            ->where('pago_em', '>=', $agora->subDays(Arrependimento::DIAS)->subDay())
            ->orderByDesc('pago_em')
            ->get();

        foreach ($compras as $compra) {
            $lotes = LoteCredito::query()->where('invoice_id', $compra->id)->pluck('id');
            $dias = Arrependimento::diasDesde(
                CarbonImmutable::instance($compra->pago_em),
                $agora,
                $lotes->isEmpty() || ConsumoAnalise::query()->whereIn('lote_credito_id', $lotes)->exists(),
            );

            if ($dias !== null) {
                return $dias;
            }
        }

        return null;
    }

    private static function data(\DateTimeInterface $d): string
    {
        return DataExibicao::data($d);
    }
}
