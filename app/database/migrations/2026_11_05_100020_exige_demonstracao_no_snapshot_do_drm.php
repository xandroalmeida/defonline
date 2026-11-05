<?php

declare(strict_types=1);

use App\Domain\Drm\Competencia;
use App\Domain\Drm\MotorDrm;
use App\Domain\Drm\Setor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * STORY-048 — um mês fechado sem `demonstracao` não pode existir (ADR-007, STORY-047).
 *
 * Desde a STORY-047 a coluna era gravada como `[]` porque o motor não existia, e ela é
 * imutável: sem este passo, esses meses ficariam sem snapshot para sempre.
 *
 * 1. **Backfill — exceção declarada à imutabilidade.** Só atinge linhas cuja `demonstracao`
 *    nunca foi calculada (sem `linhas`). O motor roda sobre o `lancamento_payload` já
 *    gravado, com o `gerado_em` original como carimbo (nenhum relógio), e a linha passa a
 *    carregar a versão do motor que produziu o snapshot. Em banco sem esses meses (produção
 *    antes do lançamento do módulo) não faz nada.
 * 2. **CHECK.** A partir daqui o banco recusa uma linha cuja demonstração não traga os dois
 *    modos ou cuja versão divirja da coluna `motor_drm_version`. O `COALESCE` é necessário:
 *    CHECK deixa passar `NULL`, e `jsonb_typeof` de caminho inexistente é `NULL` — sem ele, `[]`
 *    passaria.
 */
return new class extends Migration
{
    public function up(): void
    {
        $motor = new MotorDrm;

        DB::table('drm_lancamentos')
            ->whereRaw("NOT (jsonb_typeof(demonstracao) = 'object' AND jsonb_typeof(demonstracao->'linhas') = 'object')")
            ->orderBy('id')
            ->each(function (object $linha) use ($motor): void {
                /** @var array<string, mixed> $payload */
                $payload = json_decode((string) $linha->lancamento_payload, true, flags: JSON_THROW_ON_ERROR);
                $demonstracao = $motor->calcular(
                    $payload,
                    Setor::from($linha->setor),
                    Competencia::deTexto(substr((string) $linha->competencia, 0, 7)),
                    CarbonImmutable::parse((string) $linha->gerado_em),
                );

                DB::table('drm_lancamentos')->where('id', $linha->id)->update([
                    'demonstracao' => json_encode($demonstracao, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'motor_drm_version' => $demonstracao['motor_drm_version'],
                ]);
            });

        DB::statement(<<<'SQL'
            ALTER TABLE drm_lancamentos ADD CONSTRAINT drm_lancamentos_demonstracao_check CHECK (
                COALESCE(
                    jsonb_typeof(demonstracao->'linhas'->'fora') = 'array'
                    AND jsonb_typeof(demonstracao->'linhas'->'dentro') = 'array'
                    AND demonstracao->>'motor_drm_version' = motor_drm_version,
                    false
                )
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE drm_lancamentos DROP CONSTRAINT IF EXISTS drm_lancamentos_demonstracao_check');
    }
};
