<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\TermoTipo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Model `term_acceptances` — append-only (STORY-012 CA-3, mesmo padrão de
 * `audit_logs` e `evento_produto`; ADR-003 §Decisão 4).
 *
 * Update e delete bloqueados no model (defesa de aplicação) E pelo GRANT do
 * Postgres no role `defonline_app` (defesa em profundidade — ADR-005 §7.5).
 *
 * @property string $id
 * @property string $usuario_id
 * @property TermoTipo $termo_tipo
 * @property bool $aceito
 * @property string $versao
 * @property string $conteudo_hash
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $aceito_at
 */
final class TermAcceptance extends Model
{
    protected $table = 'term_acceptances';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'aceito_at' => 'datetime',
        'aceito' => 'boolean',
        'termo_tipo' => TermoTipo::class,
    ];

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new RuntimeException('term_acceptances é append-only (STORY-012). Update não permitido.');
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('term_acceptances é append-only (STORY-012). Delete não permitido.');
    }
}
