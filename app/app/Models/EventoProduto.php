<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Model `evento_produto` — append-only (ADR-004 §Decisão 2).
 *
 * Update e delete são rejeitados no nível do model (defesa de aplicação) E rejeitados
 * pelo GRANT do Postgres no role `defonline_app` (defesa em profundidade — ADR-005 §7.5).
 */
final class EventoProduto extends Model
{
    protected $table = 'evento_produto';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'propriedades' => AsArrayObject::class,
        'ocorrido_em' => 'datetime',
        'inserido_em' => 'datetime',
    ];

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new RuntimeException('evento_produto é append-only (ADR-004). Update não permitido.');
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('evento_produto é append-only (ADR-004). Delete não permitido.');
    }
}
