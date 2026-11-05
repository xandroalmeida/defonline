<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\StatusAssinatura;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
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
 */
#[Fillable(['usuario_id', 'plano_id', 'trial', 'status', 'ciclo', 'data_inicio', 'proximo_vencimento', 'data_cancelamento', 'cota_snapshot', 'cota_restante_ciclo', 'aviso_d2_em', 'aviso_d0_em', 'provedor', 'id_externo', 'preco_snapshot_centavos', 'periodos_pagos', 'proxima_cota_em', 'cartao_bandeira', 'cartao_ultimos4', 'aviso_renovacao_periodo', 'atraso_desde', 'atraso_cobranca_id', 'tentativas_cobranca', 'proxima_tentativa_em', 'atraso_aviso_em', 'encerramento_pendente_no_provedor', 'encerrada_em', 'meio_pagamento', 'aviso_renovacao_pix_para'])]
final class Assinatura extends Model
{
    use HasUuids;

    /** Com offset (IDR-018/025): a sessão do banco já é UTC; o offset explícito é só defesa contra outra sessão. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $table = 'assinaturas';

    /** Plano anual pago com Pix: não renova sozinho (STORY-080). */
    public function pagoComPix(): bool
    {
        return $this->meio_pagamento === 'pix';
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
        ];
    }
}
