<?php

declare(strict_types=1);

namespace App\Observabilidade;

use App\Models\AuditLog;
use App\Support\RequestId;
use Illuminate\Support\Str;

/**
 * Helper de gravação de audit log (ADR-003 §Decisão 4).
 *
 * Audit log é o registro jurídico — preserva PII (exigência de 5-10 anos, RNF §7.3).
 * Não confundir com `evento_produto` (sem PII) nem com log de aplicação (mascarado).
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $context
     */
    public static function log(
        string $action,
        string $subjectType,
        ?string $subjectId = null,
        ?string $actorType = null,
        ?string $actorId = null,
        ?string $usuarioId = null,
        ?array $before = null,
        ?array $after = null,
        ?array $context = null,
    ): AuditLog {
        return AuditLog::create([
            'id' => (string) Str::uuid7(),
            'request_id' => RequestId::get(),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'usuario_id' => $usuarioId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $before,
            'after' => $after,
            'context' => $context,
        ]);
    }
}
