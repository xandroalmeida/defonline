<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `quiz_rascunhos` — rascunho parcial do quiz de diagnóstico entre sessões (STORY-027 CA-6).
 *
 * Tabela separada por decisão da IDR-010 — `diagnosticos` é snapshot imutável e
 * só recebe registros no submit final que dispara o motor. Rascunho vive aqui até
 * `expires_at` (default 90 dias — espec §6.4) ou até ser convertido em Diagnóstico.
 *
 * Multi-tenancy: `usuario_id` denormalizado para Global Scope direto sem JOIN
 * (ADR-003 §Decisão 1). Unique parcial garante 1 rascunho ativo por par
 * (Usuário, Empresa Analisada) — ignorando soft-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_rascunhos', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('usuario_id');
            $table->foreign('usuario_id')
                ->references('id')->on('usuarios')
                ->onDelete('restrict');

            $table->uuid('empresa_analisada_id');
            $table->foreign('empresa_analisada_id')
                ->references('id')->on('empresas_analisadas')
                ->onDelete('restrict');

            // Respostas parciais do quiz (Q01..Q23 do Anexo A) sem canonicalização —
            // canonicalização é trabalho do motor, no submit final.
            $table->jsonb('quiz_payload');

            // Bloco em que o Roberto parou (1..4). CHECK abaixo.
            $table->smallInteger('ultimo_bloco_preenchido');

            // Expiração — refresh a cada save. Query "rascunhos ativos" filtra `expires_at > now()`.
            $table->timestampTz('expires_at');

            $table->timestampsTz();
            $table->timestampTz('deleted_at')->nullable();

            $table->index(['usuario_id', 'expires_at'], 'quiz_rascunhos_usuario_expires_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE quiz_rascunhos
            ADD CONSTRAINT quiz_rascunhos_bloco_check
            CHECK (ultimo_bloco_preenchido BETWEEN 1 AND 4)
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX quiz_rascunhos_usuario_empresa_unique
            ON quiz_rascunhos (usuario_id, empresa_analisada_id)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_rascunhos');
    }
};
