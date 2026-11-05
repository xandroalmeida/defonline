<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-081 — uma troca de cartão em andamento ou concluída. `id` é o `externalReference` enviado ao
 * provedor. Estados: `pendente` (checkout aberto), `concluida`, `expirada`, `cancelada`, `falhou`.
 *
 * @property string $id
 * @property string $usuario_id
 * @property string $assinatura_id
 * @property string $provedor
 * @property string $status
 * @property string|null $checkout_id_externo
 * @property string|null $checkout_url
 * @property Carbon|null $checkout_expira_em
 * @property bool $cobra_agora
 * @property string|null $antiga_id_externo
 * @property string|null $nova_id_externo
 * @property Carbon|null $concluida_em
 * @property Carbon|null $antiga_cancelada_em
 */
final class TrocaDeCartao extends Model
{
    use HasUuids;

    /** Com offset (IDR-018): o banco local roda em America/Sao_Paulo e a aplicação em UTC. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $table = 'trocas_de_cartao';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cobra_agora' => 'boolean',
            'checkout_expira_em' => 'datetime',
            'concluida_em' => 'datetime',
            'antiga_cancelada_em' => 'datetime',
        ];
    }

    /** O checkout ainda pode ser concluído pelo usuário. */
    public function aguardando(): bool
    {
        return $this->status === 'pendente' && $this->checkout_expira_em !== null && $this->checkout_expira_em->isFuture();
    }
}
