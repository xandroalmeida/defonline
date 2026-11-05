<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas do módulo DRM ("Raio X do Negócio") — ADR-007, STORY-047.
 *
 * - `drm_lancamentos`: uma linha por VERSÃO de competência, append-only. A vigente é
 *   a que não tem `substituido_por_id`; o índice único parcial é o árbitro da regra
 *   B1 e do duplo submit (a aplicação trata a violação como "já existe").
 * - `drm_rascunhos`: padrão de `quiz_rascunhos` + competência e origem.
 * - `drm_preferencias`: modo de cada grupo e posição do pró-labore, por empresa.
 *
 * Zero FK para `diagnosticos`/`quiz_rascunhos`. Competência futura (B2) NÃO é CHECK:
 * depende de `now()`, que o Postgres recusa em constraint — é validação de aplicação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drm_lancamentos', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignUuid('empresa_analisada_id')->constrained('empresas_analisadas')->restrictOnDelete();

            $table->date('competencia');
            $table->integer('versao');
            $table->uuid('substituido_por_id')->nullable();

            // Setor no momento do lançamento: a estrutura de campos depende dele.
            $table->string('setor', 16);
            $table->jsonb('lancamento_payload');
            $table->string('payload_hash', 64);
            // Saída do motor (STORY-048). Até lá, `{}`.
            $table->jsonb('demonstracao');
            // Fora do hash: ato do usuário, não insumo de cálculo.
            $table->jsonb('alertas_aceitos')->default('[]');
            $table->string('motor_drm_version', 16);
            $table->timestampTz('gerado_em');

            $table->foreignUuid('personificado_por_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->timestampsTz();
            $table->timestampTz('deleted_at')->nullable();

            $table->index('usuario_id', 'drm_lancamentos_usuario_idx');
            $table->index('motor_drm_version', 'drm_lancamentos_motor_idx');
        });

        Schema::table('drm_lancamentos', function (Blueprint $table) {
            // DEFERRABLE INITIALLY DEFERRED: retificar (STORY-050) precisa apontar a versão N para a
            // N+1 ANTES de inserir a N+1, porque o índice único parcial de vigência recusa duas
            // vigentes ao mesmo tempo e índice único não é adiável. Com a FK adiada, a ordem na
            // transação é UPDATE do ponteiro → INSERT da nova versão, e a FK é conferida no COMMIT.
            $table->foreign('substituido_por_id')->references('id')->on('drm_lancamentos')
                ->restrictOnDelete()->deferrable()->initiallyImmediate(false);
        });

        DB::statement('ALTER TABLE drm_lancamentos ADD CONSTRAINT drm_lancamentos_dia01_check CHECK (EXTRACT(DAY FROM competencia) = 1)');
        DB::statement('ALTER TABLE drm_lancamentos ADD CONSTRAINT drm_lancamentos_versao_check CHECK (versao >= 1)');
        DB::statement("ALTER TABLE drm_lancamentos ADD CONSTRAINT drm_lancamentos_setor_check CHECK (setor IN ('industria','comercio','servicos'))");
        DB::statement("ALTER TABLE drm_lancamentos ADD CONSTRAINT drm_lancamentos_hash_check CHECK (payload_hash ~ '^[0-9a-f]{64}\$')");
        DB::statement("ALTER TABLE drm_lancamentos ADD CONSTRAINT drm_lancamentos_motor_check CHECK (motor_drm_version ~ '^\\d+\\.\\d+\\.\\d+\$')");

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX drm_lancamentos_vigente_unique
            ON drm_lancamentos (empresa_analisada_id, competencia)
            WHERE substituido_por_id IS NULL AND deleted_at IS NULL
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX drm_lancamentos_versao_unique
            ON drm_lancamentos (empresa_analisada_id, competencia, versao)
            WHERE deleted_at IS NULL
        SQL);

        Schema::create('drm_rascunhos', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignUuid('empresa_analisada_id')->constrained('empresas_analisadas')->restrictOnDelete();

            $table->date('competencia');
            $table->string('origem', 16)->default('inicial');
            $table->integer('base_versao')->nullable();
            // Valores parciais + modos + repetidos[] + progresso. Sem canonicalização: é trabalho do fechamento.
            $table->jsonb('rascunho_payload');
            $table->timestampTz('expires_at');

            $table->foreignUuid('personificado_por_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->timestampsTz();
            $table->timestampTz('deleted_at')->nullable();

            $table->index(['usuario_id', 'expires_at'], 'drm_rascunhos_usuario_expires_idx');
        });

        DB::statement('ALTER TABLE drm_rascunhos ADD CONSTRAINT drm_rascunhos_dia01_check CHECK (EXTRACT(DAY FROM competencia) = 1)');
        DB::statement("ALTER TABLE drm_rascunhos ADD CONSTRAINT drm_rascunhos_origem_check CHECK (origem IN ('inicial','retificacao'))");
        DB::statement("ALTER TABLE drm_rascunhos ADD CONSTRAINT drm_rascunhos_base_versao_check CHECK ((origem = 'retificacao') = (base_versao IS NOT NULL))");
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX drm_rascunhos_usuario_empresa_competencia_unique
            ON drm_rascunhos (usuario_id, empresa_analisada_id, competencia)
            WHERE deleted_at IS NULL
        SQL);

        Schema::create('drm_preferencias', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignUuid('empresa_analisada_id')->unique()->constrained('empresas_analisadas')->restrictOnDelete();

            $table->string('posicao_prolabore', 8)->default('fora');
            // Default `detalhado` no 1º lançamento: PDR-007 §2 (override do `agregado` esboçado no ADR-007).
            $table->string('modo_custo', 16)->default('detalhado');
            $table->string('modo_vendas', 16)->default('detalhado');
            $table->string('modo_administrativas', 16)->default('detalhado');
            $table->string('modo_financeiras', 16)->default('detalhado');

            $table->foreignUuid('personificado_por_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE drm_preferencias ADD CONSTRAINT drm_preferencias_prolabore_check CHECK (posicao_prolabore IN ('fora','dentro'))");
        foreach (['custo', 'vendas', 'administrativas', 'financeiras'] as $grupo) {
            DB::statement("ALTER TABLE drm_preferencias ADD CONSTRAINT drm_preferencias_modo_{$grupo}_check CHECK (modo_{$grupo} IN ('agregado','detalhado'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('drm_preferencias');
        Schema::dropIfExists('drm_rascunhos');
        Schema::dropIfExists('drm_lancamentos');
    }
};
