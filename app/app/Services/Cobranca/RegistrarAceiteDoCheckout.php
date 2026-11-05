<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\TermoTipo;
use App\Models\TermAcceptance;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Support\TermosVigentes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * STORY-076/077 CA-1 — aceite do Termo de Adesão e da Política vigentes no checkout (data, IP, hash).
 * Devolve o id do aceite do Termo, que a Invoice guarda.
 */
final class RegistrarAceiteDoCheckout
{
    public function executar(Usuario $usuario, ?string $ip, ?string $userAgent): string
    {
        return DB::transaction(function () use ($usuario, $ip, $userAgent): string {
            $termoAdesaoId = null;

            foreach ([TermoTipo::TermoAdesao, TermoTipo::Lgpd] as $tipo) {
                $vigente = TermosVigentes::para($tipo);
                $id = (string) Str::uuid7();

                TermAcceptance::query()->create([
                    'id' => $id,
                    'usuario_id' => $usuario->id,
                    'termo_tipo' => $tipo->value,
                    'aceito' => true,
                    'versao' => $vigente->versao,
                    'conteudo_hash' => $vigente->conteudoHash,
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                ]);

                AuditLogger::log(
                    action: 'termo.aceito',
                    subjectType: 'TermAcceptance',
                    actorType: 'user',
                    actorId: $usuario->id,
                    usuarioId: $usuario->id,
                    after: ['termo_tipo' => $tipo->value, 'versao' => $vigente->versao, 'contexto' => 'checkout'],
                );

                $termoAdesaoId ??= $id;
            }

            return (string) $termoAdesaoId;
        });
    }
}
