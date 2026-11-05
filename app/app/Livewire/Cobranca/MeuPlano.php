<?php

declare(strict_types=1);

namespace App\Livewire\Cobranca;

use App\Domain\Cobranca\Arrependimento;
use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\LoteVisto;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\RenovacaoPix;
use App\Domain\Cobranca\SituacaoDoPlano;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\StatusLote;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\ConsumoAnalise;
use App\Models\LoteCredito;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\TrocaDeCartao;
use App\Models\Usuario;
use App\Services\Cobranca\GerirRenovacao;
use App\Services\Cobranca\GestaoNegada;
use App\Services\Cobranca\TrocarCartao;
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
            'cobrancas' => $cobrancas,
            'cobrancasFalharam' => $cobrancasFalharam,
            'personificando' => $personificando,
            'troca' => $troca,
            'cartao' => $this->cartao($assinatura),
            'renovaCotaEm' => $assinatura === null ? null : CarbonImmutable::instance($assinatura->proxima_cota_em ?? $assinatura->proximo_vencimento),
            'arrependimentoDias' => $this->arrependimento($assinatura, $situacao, $agora),
            'canalArrependimento' => Arrependimento::CANAL,
            'planos' => $situacao === SituacaoDoPlano::SemPlano && $lotes === [] ? Plano::query()->where('ativo', true)->orderBy('ordem')->get() : collect(),
            'pacotes' => $situacao === SituacaoDoPlano::SemPlano && $lotes === [] ? PacoteCredito::query()->where('ativo', true)->orderBy('ordem')->get() : collect(),
            'trialElegivel' => $situacao === SituacaoDoPlano::SemPlano && $lotes === [] && app(Carteira::class)->trialElegivel($usuario->id),
            'urlTeste' => RotasCobranca::url(RotasCobranca::TESTE),
            'urlPlanos' => Route::has('planos') ? route('planos') : null,
            'urlComprar' => RotasCobranca::url(RotasCobranca::COMPRAR),
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
            ->where('status', StatusLote::Ativo->value)
            ->where('quantidade_restante', '>', 0)
            ->where('data_expiracao', '>', $agora)
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

    private static function data(\DateTimeInterface $d): string
    {
        return DataExibicao::data($d);
    }
}
