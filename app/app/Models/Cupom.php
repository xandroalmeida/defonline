<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cobranca\CupomRegras;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MotivoRecusaCupom;
use App\Domain\Cobranca\TipoDesconto;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * STORY-072 / PDR-020 / spec §2.5 — cupom de desconto criado no backoffice.
 *
 * @property string $id
 * @property string $codigo
 * @property TipoDesconto $tipo
 * @property int $valor
 * @property Carbon $vigencia_inicio
 * @property Carbon $vigencia_fim
 * @property int|null $limite_usos
 * @property int|null $ciclos_duracao
 * @property bool $ativo
 * @property string|null $alterado_por_usuario_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['codigo', 'tipo', 'valor', 'vigencia_inicio', 'vigencia_fim', 'limite_usos', 'ciclos_duracao', 'ativo', 'alterado_por_usuario_id'])]
final class Cupom extends Model
{
    use HasUuids;

    protected $table = 'cupons';

    protected function casts(): array
    {
        return [
            'tipo' => TipoDesconto::class,
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'ativo' => 'boolean',
        ];
    }

    /** @return HasMany<CupomItem, $this> */
    public function itens(): HasMany
    {
        return $this->hasMany(CupomItem::class, 'cupom_id');
    }

    /** @return HasMany<CupomConcessao, $this> */
    public function concessoes(): HasMany
    {
        return $this->hasMany(CupomConcessao::class, 'cupom_id');
    }

    /** @return HasMany<CupomUso, $this> */
    public function usos(): HasMany
    {
        return $this->hasMany(CupomUso::class, 'cupom_id');
    }

    /**
     * O cupom cobre o item? Plano é identificado pelo id e pelo ciclo; pacote, pelo id.
     * Nunca pelo código (STORY-071 CA-9).
     */
    public function cobre(?string $planoId, ?string $ciclo, ?string $pacoteId): bool
    {
        return $this->itens()
            ->when(
                $planoId !== null,
                fn ($q) => $q->where('plano_id', $planoId)->where('ciclo', $ciclo),
                fn ($q) => $q->where('pacote_credito_id', $pacoteId),
            )
            ->exists();
    }

    /**
     * STORY-083 CA-4 — vagas do limite seguradas por checkouts abertos de OUTROS usuários: a Invoice
     * pendente (ainda dentro do prazo) com este cupom. O uso só é gravado quando a contratação conclui;
     * até lá, a reserva impede que dois checkouts disputem a mesma última vaga.
     */
    public function reservasDeOutros(string $usuarioId): int
    {
        return Invoice::query()
            ->where('cupom_id', $this->id)
            ->where('usuario_id', '!=', $usuarioId)
            ->where('status', EstadoCobranca::Pendente->value)
            ->where(fn ($q) => $q->whereNull('checkout_expira_em')->orWhere('checkout_expira_em', '>', DB::raw('now()')))
            ->count();
    }

    /**
     * Ponto de entrada do checkout (STORY-083): por que este usuário não pode usar o cupom
     * neste item agora, ou null se pode.
     */
    public function recusaPara(string $usuarioId, ?string $planoId, ?string $ciclo, ?string $pacoteId, CarbonInterface $agora, int $reservas = 0): ?MotivoRecusaCupom
    {
        return CupomRegras::avaliarUso(
            $this->ativo,
            $this->vigencia_inicio,
            $this->vigencia_fim,
            $this->limite_usos,
            $this->usos()->count() + $reservas,
            $this->cobre($planoId, $ciclo, $pacoteId),
            $this->usos()->where('usuario_id', $usuarioId)->exists(),
            $agora,
        );
    }
}
