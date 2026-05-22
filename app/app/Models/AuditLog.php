<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Model `audit_logs` — append-only (ADR-003 §Decisão 4).
 *
 * Update e delete bloqueados no model (defesa de aplicação) E pelo GRANT do Postgres
 * no role `defonline_app` (defesa em profundidade — ADR-005 §7.5).
 */
final class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
        'before' => AsArrayObject::class,
        'after' => AsArrayObject::class,
        'context' => AsArrayObject::class,
    ];

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new RuntimeException('audit_logs é append-only (ADR-003). Update não permitido.');
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('audit_logs é append-only (ADR-003). Delete não permitido.');
    }
}
