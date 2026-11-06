<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-099 — prazo do aviso por empresa, descadastro por usuário e o estado do aviso por diagnóstico.
 * Cada diagnóstico é um ciclo: um diagnóstico novo começa sem aviso, sem precisar limpar o anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas_analisadas', function (Blueprint $table) {
            $table->unsignedSmallInteger('renovacao_diagnostico_meses')->default(6);
        });
        DB::statement('ALTER TABLE empresas_analisadas ADD CONSTRAINT empresas_analisadas_renovacao_meses_ck CHECK (renovacao_diagnostico_meses IN (3, 6, 9, 12))');

        Schema::table('usuarios', function (Blueprint $table) {
            $table->timestampTz('aviso_renovacao_descadastrado_em')->nullable();
        });

        Schema::create('avisos_renovacao_diagnostico', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('diagnostico_id')->unique()->constrained('diagnosticos')->cascadeOnDelete();
            $table->timestampTz('aviso_em')->nullable();
            $table->timestampTz('lembrete_em')->nullable();
            $table->boolean('sem_lembrete')->default(false);
            $table->timestampTz('alerta_dispensado_em')->nullable();
            $table->timestampsTz();
        });

        $this->marcarVencidosNoLancamento();
    }

    /** Quem já está com o prazo padrão vencido hoje recebe um único aviso, sem o lembrete (CA-10). */
    public function marcarVencidosNoLancamento(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO avisos_renovacao_diagnostico (id, diagnostico_id, sem_lembrete, created_at, updated_at)
            SELECT gen_random_uuid(), ultimo.id, true, now(), now()
            FROM (
                SELECT DISTINCT ON (empresa_analisada_id) id, empresa_analisada_id, gerado_em
                FROM diagnosticos
                WHERE deleted_at IS NULL
                ORDER BY empresa_analisada_id, gerado_em DESC
            ) AS ultimo
            JOIN empresas_analisadas e ON e.id = ultimo.empresa_analisada_id
            WHERE ultimo.gerado_em + make_interval(months => e.renovacao_diagnostico_meses) <= now()
            ON CONFLICT (diagnostico_id) DO NOTHING
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_renovacao_diagnostico');

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('aviso_renovacao_descadastrado_em');
        });

        DB::statement('ALTER TABLE empresas_analisadas DROP CONSTRAINT IF EXISTS empresas_analisadas_renovacao_meses_ck');
        Schema::table('empresas_analisadas', function (Blueprint $table) {
            $table->dropColumn('renovacao_diagnostico_meses');
        });
    }
};
