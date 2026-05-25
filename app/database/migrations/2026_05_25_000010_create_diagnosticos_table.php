<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `diagnosticos` — snapshot imutável de um Diagnóstico Econômico-Financeiro emitido
 * para uma Empresa Analisada (espec V2.5 §4.5/§4.7; EPIC-002).
 *
 * Decisões formalizadas em IDR-010:
 *   - `motor_version` em semver inteiro (`1.0.0`); `matrix_version` em formato datado (`dez-2025`).
 *   - Persistência por snapshot dos resultados (sem recálculo on-the-fly).
 *   - Idempotência por hash determinístico do `quiz_payload` canonicalizado (SHA-256 → `payload_hash`).
 *   - Multi-tenancy via `usuario_id` denormalizado (Global Scope sem JOIN; ADR-003 §Decisão 1).
 *   - Soft delete + anonimização diferida (ADR-003 §Decisão 5).
 *
 * STORY-026 cria este esqueleto. STORY-028 roda a migration ao implementar o motor V1.
 *
 * @see defonline-docs/project-state/decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosticos', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Tenant — denormalizado para Global Scope direto sem JOIN.
            $table->uuid('usuario_id');
            $table->foreign('usuario_id')
                ->references('id')->on('usuarios')
                ->onDelete('restrict');
            $table->index('usuario_id');

            // Empresa alvo do diagnóstico (espec V2 §1.5.2).
            $table->uuid('empresa_analisada_id');
            $table->foreign('empresa_analisada_id')
                ->references('id')->on('empresas_analisadas')
                ->onDelete('restrict');
            // Índice composto para listagem do histórico (EPIC-003): 12 meses por empresa, mais recentes primeiro.
            $table->index(['empresa_analisada_id', 'gerado_em'], 'diagnosticos_empresa_gerado_em_idx');

            // Versionamento (IDR-010 sub-decisão 1).
            $table->string('motor_version', 16);
            $table->string('matrix_version', 16);
            $table->index('motor_version');

            // Setor snapshot (matriz é setor-dependente; espec §4.5 Anexo E).
            $table->string('setor', 16);

            // Snapshot imutável dos dados de entrada e saída (IDR-010 sub-decisão 2).
            // - quiz_payload: respostas canonicalizadas (chaves ordenadas, decimais como string).
            // - indicadores_calculados: 14 indicadores com valor|null, farol, faixa, mensagens.
            // - resumo_executivo: estrutura determinística §4.7.1.
            $table->jsonb('quiz_payload');
            $table->string('payload_hash', 64);          // SHA-256 hex do quiz_payload canonicalizado.
            $table->jsonb('indicadores_calculados');
            $table->jsonb('resumo_executivo');

            // Momento do cálculo (≠ created_at se houver reprocessamento manual, não previsto no MVP).
            $table->timestampTz('gerado_em');

            $table->timestampsTz();
            $table->timestampTz('deleted_at')->nullable();
        });

        // Defesa em profundidade no banco: enum de setor via CHECK (espec V2 §4.5; Anexo E).
        DB::statement(<<<'SQL'
            ALTER TABLE diagnosticos
            ADD CONSTRAINT diagnosticos_setor_check
            CHECK (setor IN ('industria', 'comercio', 'servicos'))
        SQL);

        // motor_version no formato semver (MAJOR.MINOR.PATCH com 1-3 dígitos por componente — IDR-010).
        DB::statement(<<<'SQL'
            ALTER TABLE diagnosticos
            ADD CONSTRAINT diagnosticos_motor_version_check
            CHECK (motor_version ~ '^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$')
        SQL);

        // matrix_version no formato `mes-aaaa` (jan..dez)-AAAA — IDR-010.
        DB::statement(<<<'SQL'
            ALTER TABLE diagnosticos
            ADD CONSTRAINT diagnosticos_matrix_version_check
            CHECK (matrix_version ~ '^(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)-[0-9]{4}$')
        SQL);

        // payload_hash = SHA-256 hex (exatamente 64 chars hexadecimais minúsculos).
        DB::statement(<<<'SQL'
            ALTER TABLE diagnosticos
            ADD CONSTRAINT diagnosticos_payload_hash_check
            CHECK (payload_hash ~ '^[0-9a-f]{64}$')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosticos');
    }
};
