<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cobranca\EstadoCobranca;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * STORY-076 / ADR-010 §7 — toda cobrança do produto, com estado canônico e id no provedor.
 * `id` é o `externalReference` enviado ao provedor. Valor, quantidade e validade são o retrato
 * do pacote no momento da compra: mudar o catálogo depois não altera o que foi vendido.
 *
 * @property string $id
 * @property string $usuario_id
 * @property string $tipo
 * @property string|null $pacote_credito_id
 * @property string $descricao
 * @property int|null $quantidade_creditos
 * @property int|null $validade_meses
 * @property int $valor_centavos
 * @property EstadoCobranca $status
 * @property string $meio
 * @property string $provedor
 * @property string|null $checkout_id_externo
 * @property string|null $checkout_url
 * @property Carbon|null $checkout_expira_em
 * @property string|null $id_externo
 * @property Carbon|null $pago_em
 * @property string|null $motivo_encerramento
 * @property string|null $falha_motivo
 * @property string|null $aceite_termo_id
 * @property string|null $plano_id
 * @property string|null $ciclo
 * @property int|null $cota_snapshot
 * @property string|null $assinatura_id
 * @property bool $renovacao
 * @property string|null $cartao_bandeira
 * @property string|null $cartao_ultimos4
 * @property int|null $periodo_base
 * @property int|null $cota_extra_troca
 * @property int|null $valor_esperado_centavos
 * @property string|null $cupom_id
 * @property int|null $cupom_ciclos
 * @property int|null $preco_cheio_centavos
 * @property int $desconto_centavos
 * @property string|null $reembolso_estado
 * @property string|null $reembolso_motivo
 * @property string|null $reembolso_por_id
 * @property Carbon|null $reembolso_solicitado_em
 * @property Carbon|null $reembolsado_em
 * @property string|null $devolucao_motivo
 * @property string|null $assinatura_id_externo
 * @property int|null $reembolsado_parcial_centavos
 * @property Carbon|null $nf_emitida_em
 * @property string|null $nf_emitida_por_id
 * @property string|null $nf_numero
 * @property array<string, mixed>|null $troca_anterior
 * @property Carbon|null $created_at
 */
final class Invoice extends Model
{
    use HasUuids;

    /** Com offset (IDR-025): a sessão do banco já é UTC; o offset explícito é só defesa contra outra sessão. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $table = 'invoices';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => EstadoCobranca::class,
            'checkout_expira_em' => 'datetime',
            'pago_em' => 'datetime',
            'renovacao' => 'boolean',
            'reembolso_solicitado_em' => 'datetime',
            'reembolsado_em' => 'datetime',
            'nf_emitida_em' => 'datetime',
            'troca_anterior' => 'array',
        ];
    }

    /** Inclui conta excluída: a cobrança continua existindo para a equipe financeira (STORY-085). */
    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }

    /** O prazo do checkout passou sem pagamento (o banco ainda pode confirmar depois). */
    public function expirada(): bool
    {
        return $this->status === EstadoCobranca::Vencida
            || ($this->status === EstadoCobranca::Pendente
                && $this->checkout_expira_em !== null
                && $this->checkout_expira_em->isPast());
    }

    public function dataDeExpiracaoDosCreditos(): ?Carbon
    {
        return $this->validade_meses === null ? null : ($this->pago_em ?? now())->copy()->addMonthsNoOverflow($this->validade_meses);
    }
}
