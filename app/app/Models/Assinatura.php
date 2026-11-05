<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\DescontoDaAssinatura;
use App\Domain\Cobranca\StatusAssinatura;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * STORY-073 — assinatura do Usuário, inclusive o trial (`trial` verdadeiro e `plano_id` nulo).
 * Cota e vencimento são o que foi concedido; quem concede é a {@see Carteira}.
 *
 * @property string $id
 * @property string $usuario_id
 * @property string|null $plano_id
 * @property bool $trial
 * @property StatusAssinatura $status
 * @property string|null $ciclo
 * @property Carbon $data_inicio
 * @property Carbon $proximo_vencimento
 * @property Carbon|null $data_cancelamento
 * @property int $cota_snapshot
 * @property int $cota_restante_ciclo
 * @property Carbon|null $aviso_d2_em
 * @property Carbon|null $aviso_d0_em
 * @property string|null $provedor
 * @property string|null $id_externo
 * @property int|null $preco_snapshot_centavos
 * @property int $periodos_pagos
 * @property Carbon|null $proxima_cota_em
 * @property string|null $cartao_bandeira
 * @property string|null $cartao_ultimos4
 * @property int|null $aviso_renovacao_periodo
 * @property Carbon|null $atraso_desde
 * @property string|null $atraso_cobranca_id
 * @property int $tentativas_cobranca
 * @property Carbon|null $proxima_tentativa_em
 * @property Carbon|null $atraso_aviso_em
 * @property bool $encerramento_pendente_no_provedor
 * @property Carbon|null $encerrada_em
 * @property string $meio_pagamento
 * @property Carbon|null $aviso_renovacao_pix_para
 * @property string|null $troca_plano_id
 * @property string|null $troca_ciclo
 * @property int|null $troca_preco_centavos
 * @property Carbon|null $troca_agendada_em
 * @property bool $ajuste_provedor_pendente
 * @property string|null $cupom_id
 * @property int|null $preco_cheio_centavos
 * @property int $desconto_centavos
 * @property int|null $desconto_periodos
 * @property Carbon|null $desconto_aviso_em
 * @property bool $valor_pendente_no_provedor
 * @property int|null $troca_preco_cheio_centavos
 * @property int|null $troca_desconto_centavos
 */
#[Fillable(['usuario_id', 'plano_id', 'trial', 'status', 'ciclo', 'data_inicio', 'proximo_vencimento', 'data_cancelamento', 'cota_snapshot', 'cota_restante_ciclo', 'aviso_d2_em', 'aviso_d0_em', 'provedor', 'id_externo', 'preco_snapshot_centavos', 'periodos_pagos', 'proxima_cota_em', 'cartao_bandeira', 'cartao_ultimos4', 'aviso_renovacao_periodo', 'atraso_desde', 'atraso_cobranca_id', 'tentativas_cobranca', 'proxima_tentativa_em', 'atraso_aviso_em', 'encerramento_pendente_no_provedor', 'encerrada_em', 'meio_pagamento', 'aviso_renovacao_pix_para', 'troca_plano_id', 'troca_ciclo', 'troca_preco_centavos', 'troca_agendada_em', 'ajuste_provedor_pendente', 'cupom_id', 'preco_cheio_centavos', 'desconto_centavos', 'desconto_periodos', 'desconto_aviso_em', 'valor_pendente_no_provedor', 'troca_preco_cheio_centavos', 'troca_desconto_centavos'])]
final class Assinatura extends Model
{
    use HasUuids;

    /** Com offset (IDR-018/025): a sessão do banco já é UTC; o offset explícito é só defesa contra outra sessão. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $table = 'assinaturas';

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }

    /** @return BelongsTo<Plano, $this> */
    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    /** Plano anual pago com Pix: não renova sozinho (STORY-080). */
    public function pagoComPix(): bool
    {
        return $this->meio_pagamento === 'pix';
    }

    /** Ativada sem cobrança (cupom de desconto total): não há cartão nem Pix cadastrado (STORY-083). */
    public function semMeioDePagamento(): bool
    {
        return $this->meio_pagamento === 'nenhum';
    }

    /** O desconto contratado, como valor (STORY-083). `null` se a assinatura nunca teve desconto. */
    public function desconto(): ?DescontoDaAssinatura
    {
        if ($this->desconto_centavos <= 0 || $this->preco_cheio_centavos === null) {
            return null;
        }

        return new DescontoDaAssinatura($this->preco_cheio_centavos, $this->desconto_centavos, $this->desconto_periodos);
    }

    /**
     * STORY-082 × 083 — o desconto que vale na próxima cobrança: com downgrade agendado é o do plano novo
     * (`troca_desconto_centavos`; 0 = terminou na troca), senão o contratado.
     */
    public function descontoDaProximaCobranca(): ?DescontoDaAssinatura
    {
        if ($this->troca_plano_id === null) {
            return $this->desconto();
        }

        if ((int) $this->troca_desconto_centavos <= 0 || $this->troca_preco_cheio_centavos === null) {
            return null;
        }

        return new DescontoDaAssinatura($this->troca_preco_cheio_centavos, (int) $this->troca_desconto_centavos, $this->desconto_periodos);
    }

    /** O preço cheio da próxima cobrança (o do plano novo, se há downgrade agendado). */
    public function precoCheioDaProximaCobranca(): int
    {
        return (int) ($this->troca_plano_id !== null ? ($this->troca_preco_cheio_centavos ?? $this->troca_preco_centavos) : $this->preco_snapshot_centavos);
    }

    /** O downgrade agendado vale agora: o retrato do desconto passa a ser o do plano novo (ou some, se terminou). */
    public function aplicarRetratoDaTroca(): void
    {
        if ($this->troca_plano_id === null) {
            return;
        }

        $this->forceFill((int) $this->troca_desconto_centavos > 0
            ? ['preco_cheio_centavos' => $this->troca_preco_cheio_centavos, 'desconto_centavos' => (int) $this->troca_desconto_centavos]
            : ['preco_cheio_centavos' => null, 'desconto_centavos' => 0, 'desconto_periodos' => null])->save();
    }

    protected function casts(): array
    {
        return [
            'trial' => 'boolean',
            'status' => StatusAssinatura::class,
            'data_inicio' => 'datetime',
            'proximo_vencimento' => 'datetime',
            'data_cancelamento' => 'datetime',
            'aviso_d2_em' => 'datetime',
            'aviso_d0_em' => 'datetime',
            'proxima_cota_em' => 'datetime',
            'atraso_desde' => 'datetime',
            'proxima_tentativa_em' => 'datetime',
            'atraso_aviso_em' => 'datetime',
            'encerrada_em' => 'datetime',
            'encerramento_pendente_no_provedor' => 'boolean',
            'aviso_renovacao_pix_para' => 'datetime',
            'troca_agendada_em' => 'datetime',
            'ajuste_provedor_pendente' => 'boolean',
            'desconto_aviso_em' => 'datetime',
            'valor_pendente_no_provedor' => 'boolean',
        ];
    }
}
