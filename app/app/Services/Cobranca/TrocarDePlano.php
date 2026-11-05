<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\CalculoDeTroca;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\CobrancaNoProvedor;
use App\Domain\Cobranca\CotaMensal;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\PedidoCheckout;
use App\Domain\Cobranca\PreviaDeTroca;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Models\Plano;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Support\Personificacao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

/**
 * STORY-082 — upgrade imediato e downgrade no próximo ciclo (Termos; ajustes do ADR-010).
 *
 * - **Upgrade** (preço maior, {@see CalculoDeTroca}): a diferença proporcional é um checkout AVULSO
 *   (cartão, ou Pix no plano anual por Pix). O plano e a cota só mudam quando o provedor confirma o
 *   pagamento ({@see aplicarPaga()}); recusado, o plano atual segue (CA-2). Depois, o valor da assinatura
 *   no provedor é ajustado ({@see ajustarNoProvedor()}), com nova tentativa horária se falhar. Diferença
 *   abaixo de R$ 5,00 é aplicada sem cobrança.
 * - **Downgrade**: nada é cobrado nem muda hoje; fica agendado em `assinaturas.troca_*` e vale na
 *   renovação ({@see ConfirmarAssinatura}). O valor no provedor já é o novo, para a próxima cobrança sair certa. Dá para
 *   desfazer antes disso ({@see desfazer()}).
 * - Só planos ativos são destino (CA-5); o plano atual, mesmo desativado, segue valendo.
 * - Em atraso, cancelada, em personificação: nenhuma troca.
 */
final class TrocarDePlano
{
    public function __construct(private readonly PaymentProvider $provedor) {}

    /** A assinatura que pode trocar de plano, ou o motivo de não poder. @throws TrocaNegada */
    public function assinaturaDaTroca(Usuario $usuario, bool $paraLeitura = false): Assinatura
    {
        if (! $paraLeitura && Personificacao::ativa() !== null) {
            throw TrocaNegada::porque(TrocaNegada::ADMIN_PERSONIFICANDO);
        }

        $assinatura = Assinatura::query()
            ->where('usuario_id', $usuario->id)
            ->where('trial', false)
            ->whereIn('status', [StatusAssinatura::Ativa->value, StatusAssinatura::EmAtraso->value, StatusAssinatura::Cancelada->value])
            ->orderByDesc('data_inicio')
            ->first();

        return $this->validarEstado($assinatura);
    }

    /**
     * @throws TrocaNegada
     */
    public function previa(Assinatura $assinatura, Plano $destino, CicloCobranca $ciclo): PreviaDeTroca
    {
        $this->validarDestino($assinatura, $destino, $ciclo);

        $atual = Plano::query()->find($assinatura->plano_id);
        $cicloAtual = CicloCobranca::from((string) $assinatura->ciclo);
        $precoAtual = $assinatura->preco_snapshot_centavos ?? ($atual === null ? 0 : IniciarAssinatura::precoDe($atual, $cicloAtual));

        return CalculoDeTroca::calcular(
            $precoAtual,
            $cicloAtual,
            $assinatura->cota_snapshot,
            self::inicioDoPeriodo($assinatura, $cicloAtual),
            $assinatura->proximo_vencimento,
            CarbonImmutable::now(),
            IniciarAssinatura::precoDe($destino, $ciclo),
            $ciclo,
            $destino->cota_mensal,
        );
    }

    /**
     * @throws TrocaNegada
     * @throws ProvedorIndisponivel
     */
    public function confirmar(Usuario $usuario, Plano $destino, CicloCobranca $ciclo): ResultadoDaTroca
    {
        $assinatura = $this->assinaturaDaTroca($usuario);
        $previa = $this->previa($assinatura, $destino, $ciclo);

        if (! $previa->imediata) {
            return $this->agendar($usuario, $assinatura, $destino, $ciclo, $previa);
        }

        if ($previa->semCobranca) {
            return $this->aplicarSemCobranca($usuario, $assinatura, $destino, $ciclo, $previa);
        }

        return new ResultadoDaTroca('checkout', $previa, $this->abrirCheckout($usuario, $assinatura, $destino, $ciclo, $previa));
    }

    /**
     * CA-4 — desfaz o downgrade agendado (idempotente: sem agendamento, não faz nada).
     *
     * @throws TrocaNegada
     * @throws ProvedorIndisponivel
     */
    public function desfazer(Usuario $usuario): bool
    {
        $assinatura = $this->assinaturaDaTroca($usuario);

        if ($assinatura->troca_plano_id === null) {
            return false;
        }

        $this->empurrarAoProvedor($assinatura, (int) $assinatura->preco_snapshot_centavos, CicloCobranca::from((string) $assinatura->ciclo));

        $assinatura->forceFill(['troca_plano_id' => null, 'troca_ciclo' => null, 'troca_preco_centavos' => null, 'troca_agendada_em' => null])->save();

        AuditLogger::log(
            action: 'cobranca.troca_de_plano_desfeita',
            subjectType: 'Assinatura',
            subjectId: $assinatura->id,
            actorType: 'user',
            actorId: $usuario->id,
            usuarioId: $usuario->id,
        );

        return true;
    }

    /**
     * O provedor confirmou a diferença: o plano novo vale agora. Chamado só por {@see ConfirmarAssinatura},
     * dentro da transação e com o estado atual já conferido no provedor.
     *
     * @return array{0: 'processado'|'ignorado', 1: string|null}
     */
    public function aplicarPaga(Invoice $invoice, CobrancaNoProvedor $cobranca): array
    {
        if ($cobranca->valorCentavos !== $invoice->valor_centavos) {
            Log::error('cobranca.valor_divergente', [
                'module' => 'cobranca',
                'action' => 'processar_evento',
                'invoice_id' => $invoice->id,
                'esperado_centavos' => $invoice->valor_centavos,
                'recebido_centavos' => $cobranca->valorCentavos,
            ]);

            throw new UnexpectedValueException('Valor pago diferente do valor da invoice.');
        }

        $pagoEm = $cobranca->pagoEm !== null ? CarbonImmutable::instance($cobranca->pagoEm) : CarbonImmutable::now();

        $invoice->forceFill([
            'status' => EstadoCobranca::Paga,
            'id_externo' => $cobranca->referencia->id,
            'checkout_id_externo' => $invoice->checkout_id_externo ?? $cobranca->checkoutId,
            'pago_em' => $pagoEm,
            'cartao_bandeira' => $cobranca->cartaoBandeira,
            'cartao_ultimos4' => $cobranca->cartaoUltimos4,
        ])->save();

        $assinatura = Assinatura::query()->lockForUpdate()->find($invoice->assinatura_id);
        $plano = $invoice->plano_id === null ? null : Plano::query()->find($invoice->plano_id);

        // O dinheiro entrou, mas o ciclo seguiu em frente (renovou, foi cancelado ou já trocou): não dá para
        // aplicar a troca sobre uma base que mudou. Vira alerta para devolução manual (STORY-087).
        if ($assinatura === null || $plano === null || $assinatura->status !== StatusAssinatura::Ativa || $assinatura->periodos_pagos !== $invoice->periodo_base) {
            Log::error('cobranca.troca_paga_sem_efeito', [
                'module' => 'cobranca',
                'action' => 'processar_evento',
                'invoice_id' => $invoice->id,
                'assinatura_id' => $invoice->assinatura_id,
            ]);

            return ['processado', $invoice->id];
        }

        $this->aplicarLocal($assinatura, $plano, CicloCobranca::from((string) $invoice->ciclo), IniciarAssinatura::precoDe($plano, CicloCobranca::from((string) $invoice->ciclo)), $pagoEm);

        AuditLogger::log(
            action: 'cobranca.troca_de_plano_aplicada',
            subjectType: 'Assinatura',
            subjectId: $assinatura->id,
            actorType: 'system',
            usuarioId: $assinatura->usuario_id,
            after: ['plano_id' => $plano->id, 'ciclo' => $invoice->ciclo, 'diferenca_centavos' => $invoice->valor_centavos, 'cota' => $plano->cota_mensal],
        );

        return ['processado', $invoice->id];
    }

    /** Rotina horária: o ajuste de valor que o provedor não aceitou na hora. @return int quantos foram ajustados */
    public function reenviarAjustesPendentes(): int
    {
        $feitos = 0;

        foreach (Assinatura::query()->where('ajuste_provedor_pendente', true)->whereNotNull('id_externo')->pluck('id') as $id) {
            $a = Assinatura::query()->find($id);

            if ($a !== null && $this->ajustarNoProvedor($a)) {
                $feitos++;
            }
        }

        return $feitos;
    }

    /** O valor, o ciclo e a próxima cobrança do provedor passam a ser os da assinatura. Falhou = marcado para repetir. */
    public function ajustarNoProvedor(Assinatura $assinatura): bool
    {
        if ($assinatura->id_externo === null) {
            return true;
        }

        try {
            $this->empurrarAoProvedor($assinatura, (int) $assinatura->preco_snapshot_centavos, CicloCobranca::from((string) $assinatura->ciclo));
        } catch (ProvedorIndisponivel $e) {
            $assinatura->forceFill(['ajuste_provedor_pendente' => true])->save();

            Log::error('cobranca.ajuste_de_valor_pendente', [
                'module' => 'cobranca',
                'action' => 'trocar_de_plano',
                'assinatura_id' => $assinatura->id,
                'motivo' => $e->getMessage(),
            ]);

            return false;
        }

        if ($assinatura->ajuste_provedor_pendente) {
            $assinatura->forceFill(['ajuste_provedor_pendente' => false])->save();
        }

        return true;
    }

    /** A troca agendada (se houver) com o plano de destino, para Meu plano. */
    public static function agendada(Assinatura $assinatura): ?Plano
    {
        return $assinatura->troca_plano_id === null ? null : Plano::query()->find($assinatura->troca_plano_id);
    }

    /** O início do período em curso (para a proporção): a data de início ou o fim do período anterior. */
    private static function inicioDoPeriodo(Assinatura $assinatura, CicloCobranca $ciclo): CarbonImmutable
    {
        return $assinatura->periodos_pagos <= 1
            ? CarbonImmutable::instance($assinatura->data_inicio)
            : $ciclo->vencimento($assinatura->data_inicio, $assinatura->periodos_pagos - 1);
    }

    /** @throws TrocaNegada */
    private function validarEstado(?Assinatura $assinatura): Assinatura
    {
        if ($assinatura === null) {
            throw TrocaNegada::porque(TrocaNegada::SEM_ASSINATURA);
        }

        return match ($assinatura->status) {
            StatusAssinatura::EmAtraso => throw TrocaNegada::porque(TrocaNegada::EM_ATRASO),
            StatusAssinatura::Cancelada => throw TrocaNegada::porque(TrocaNegada::CANCELADA),
            StatusAssinatura::Ativa => $assinatura,
            default => throw TrocaNegada::porque(TrocaNegada::SEM_ASSINATURA),
        };
    }

    /** @throws TrocaNegada */
    private function validarDestino(Assinatura $assinatura, Plano $destino, CicloCobranca $ciclo): void
    {
        if (! $destino->ativo) {
            throw TrocaNegada::porque(TrocaNegada::PLANO_INDISPONIVEL);
        }

        if ($destino->id === $assinatura->plano_id && $ciclo->value === $assinatura->ciclo) {
            throw TrocaNegada::porque(TrocaNegada::IGUAL);
        }

        if ($assinatura->pagoComPix() && $ciclo !== CicloCobranca::Anual) {
            throw TrocaNegada::porque(TrocaNegada::PIX_SO_ANUAL);
        }
    }

    private function agendar(Usuario $usuario, Assinatura $assinatura, Plano $destino, CicloCobranca $ciclo, PreviaDeTroca $previa): ResultadoDaTroca
    {
        $this->empurrarAoProvedor($assinatura, $previa->precoNovoCentavos, $ciclo);

        $assinatura->forceFill([
            'troca_plano_id' => $destino->id,
            'troca_ciclo' => $ciclo->value,
            'troca_preco_centavos' => $previa->precoNovoCentavos,
            'troca_agendada_em' => now(),
        ])->save();

        AuditLogger::log(
            action: 'cobranca.troca_de_plano_agendada',
            subjectType: 'Assinatura',
            subjectId: $assinatura->id,
            actorType: 'user',
            actorId: $usuario->id,
            usuarioId: $usuario->id,
            after: ['plano_id' => $destino->id, 'ciclo' => $ciclo->value, 'vigente_em' => $previa->vigenteAte->toIso8601String()],
        );

        return new ResultadoDaTroca('agendada', $previa);
    }

    private function aplicarSemCobranca(Usuario $usuario, Assinatura $assinatura, Plano $destino, CicloCobranca $ciclo, PreviaDeTroca $previa): ResultadoDaTroca
    {
        DB::transaction(function () use ($usuario, $assinatura, $destino, $ciclo, $previa): void {
            $travada = Assinatura::query()->lockForUpdate()->findOrFail($assinatura->id);

            if ($travada->status !== StatusAssinatura::Ativa || $travada->periodos_pagos !== $assinatura->periodos_pagos) {
                throw TrocaNegada::porque(TrocaNegada::MUDOU);
            }

            $this->aplicarLocal($travada, $destino, $ciclo, $previa->precoNovoCentavos, CarbonImmutable::now());

            AuditLogger::log(
                action: 'cobranca.troca_de_plano_aplicada',
                subjectType: 'Assinatura',
                subjectId: $travada->id,
                actorType: 'user',
                actorId: $usuario->id,
                usuarioId: $usuario->id,
                after: ['plano_id' => $destino->id, 'ciclo' => $ciclo->value, 'diferenca_centavos' => $previa->aPagarCentavos, 'sem_cobranca' => true, 'cota' => $destino->cota_mensal],
            );
        });

        return new ResultadoDaTroca('aplicada', $previa);
    }

    /** O plano novo, a cota e o preço na carteira; se o ciclo mudou, um período novo começa agora. */
    private function aplicarLocal(Assinatura $a, Plano $plano, CicloCobranca $ciclo, int $preco, CarbonImmutable $agora): void
    {
        $cicloMudou = $ciclo->value !== $a->ciclo;
        // O que já foi gasto no ciclo continua gasto: a cota nova vale "a partir de agora".
        $gastas = max(0, $a->cota_snapshot - $a->cota_restante_ciclo);

        $campos = [
            'plano_id' => $plano->id,
            'ciclo' => $ciclo->value,
            'preco_snapshot_centavos' => $preco,
            'cota_snapshot' => $plano->cota_mensal,
            'cota_restante_ciclo' => $cicloMudou ? $plano->cota_mensal : max(0, $plano->cota_mensal - $gastas),
            'troca_plano_id' => null,
            'troca_ciclo' => null,
            'troca_preco_centavos' => null,
            'troca_agendada_em' => null,
        ];

        if ($cicloMudou) {
            $vencimento = $ciclo->vencimento($agora);
            $campos += [
                'data_inicio' => $agora,
                'periodos_pagos' => 1,
                'proximo_vencimento' => $vencimento,
                'proxima_cota_em' => $ciclo === CicloCobranca::Anual ? CotaMensal::proxima($agora, $agora, $vencimento) : null,
                'aviso_renovacao_periodo' => null,
            ];
        }

        $a->forceFill($campos)->save();
        $this->ajustarNoProvedor($a);
    }

    /** @throws ProvedorIndisponivel */
    private function empurrarAoProvedor(Assinatura $assinatura, int $valorCentavos, CicloCobranca $ciclo): void
    {
        if ($assinatura->id_externo === null) {
            return;
        }

        $this->provedor->changeSubscriptionValue(
            new ReferenciaProvedor((string) $assinatura->provedor, $assinatura->id_externo),
            $valorCentavos,
            $ciclo,
            $assinatura->proximo_vencimento->toDateTimeImmutable(),
        );
    }

    /** @throws ProvedorIndisponivel */
    private function abrirCheckout(Usuario $usuario, Assinatura $assinatura, Plano $destino, CicloCobranca $ciclo, PreviaDeTroca $previa): Invoice
    {
        $meio = $assinatura->pagoComPix() ? MeioPagamento::Pix : MeioPagamento::Cartao;

        // Clique repetido ou volta à tela: o mesmo checkout ainda vale se a conta é a mesma.
        $existente = Invoice::query()
            ->where('assinatura_id', $assinatura->id)
            ->where('tipo', 'troca_de_plano')
            ->where('plano_id', $destino->id)
            ->where('ciclo', $ciclo->value)
            ->where('valor_centavos', $previa->aPagarCentavos)
            ->where('periodo_base', $assinatura->periodos_pagos)
            ->where('meio', $meio->value)
            ->where('provedor', $this->provedor->nome())
            ->where('status', EstadoCobranca::Pendente->value)
            ->whereNotNull('checkout_url')
            ->where('checkout_expira_em', '>', DB::raw('now()'))
            ->latest('created_at')
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        $invoice = Invoice::query()->create([
            'usuario_id' => $usuario->id,
            'tipo' => 'troca_de_plano',
            'plano_id' => $destino->id,
            'ciclo' => $ciclo->value,
            'cota_snapshot' => $destino->cota_mensal,
            'descricao' => "Troca para o plano {$destino->nome} · {$ciclo->value}",
            'valor_centavos' => $previa->aPagarCentavos,
            'status' => EstadoCobranca::Pendente,
            'meio' => $meio->value,
            'provedor' => $this->provedor->nome(),
            'assinatura_id' => $assinatura->id,
            'periodo_base' => $assinatura->periodos_pagos,
        ]);

        $pix = $meio === MeioPagamento::Pix;

        app(AbrirCheckoutDaInvoice::class)->executar($invoice, new PedidoCheckout(
            referenciaInterna: $invoice->id,
            descricao: $invoice->descricao,
            valorCentavos: $previa->aPagarCentavos,
            meios: [$meio],
            expiraEmMinutos: $pix
                ? (int) config('services.payments.pix_expiracao_minutos', 30)
                : (int) config('services.payments.cartao_expiracao_minutos', 60),
            urlSucesso: route('plano.trocar.retorno', $invoice),
            urlCancelamento: route('plano.trocar', ['plano' => $destino->id, 'ciclo' => $ciclo->value]),
            urlExpirado: route('plano.trocar.retorno', $invoice),
        ), 'iniciar_troca_de_plano');

        AuditLogger::log(
            action: 'cobranca.checkout_troca_de_plano_criado',
            subjectType: 'Invoice',
            subjectId: $invoice->id,
            actorType: 'user',
            actorId: $usuario->id,
            usuarioId: $usuario->id,
            after: ['valor_centavos' => $previa->aPagarCentavos, 'plano_id' => $destino->id, 'ciclo' => $ciclo->value, 'provedor' => $invoice->provedor, 'meio' => $meio->value],
        );

        return $invoice;
    }
}
