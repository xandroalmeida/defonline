<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cobranca\StatusLote;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-073 — lote de créditos avulsos do Usuário (consumo FIFO por vencimento, espec §4.3).
 *
 * @property string $id
 * @property string $usuario_id
 * @property string|null $invoice_id
 * @property int $quantidade_comprada
 * @property int $quantidade_restante
 * @property StatusLote $status
 * @property Carbon $data_compra
 * @property Carbon $data_expiracao
 */
#[Fillable(['usuario_id', 'invoice_id', 'quantidade_comprada', 'quantidade_restante', 'status', 'data_compra', 'data_expiracao'])]
final class LoteCredito extends Model
{
    use HasUuids;

    /** Com offset (IDR-025): a sessão do banco já é UTC; o offset explícito é só defesa contra outra sessão. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $table = 'lotes_credito';

    /**
     * Crédito utilizável: ativo, com saldo, sem estorno em curso e dentro da validade.
     *
     * @param  Builder<LoteCredito>  $query
     */
    public function scopeDisponiveis(Builder $query, CarbonInterface $agora): void
    {
        $query->where('status', StatusLote::Ativo->value)
            ->where('quantidade_restante', '>', 0)
            ->where('data_expiracao', '>', $agora);
        $this->scopeSemEstornoEmCurso($query);
    }

    /**
     * STORY-092 (D-12) — o lote cuja compra tem estorno pedido e ainda não concluído (em andamento ou aguardando a
     * autorização do admin no provedor) não se consome: o dinheiro está sendo devolvido. Se o estorno for negado,
     * a marca na cobrança volta a nulo e o lote reaparece.
     *
     * @param  Builder<LoteCredito>  $query
     */
    public function scopeSemEstornoEmCurso(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereNull('invoice_id')
            ->orWhereNotIn('invoice_id', Invoice::query()->select('id')->whereIn('reembolso_estado', ['em_andamento', 'aguardando_autorizacao'])));
    }

    protected function casts(): array
    {
        return [
            'status' => StatusLote::class,
            'data_compra' => 'datetime',
            'data_expiracao' => 'datetime',
        ];
    }
}
