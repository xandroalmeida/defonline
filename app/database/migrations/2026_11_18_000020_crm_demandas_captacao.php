<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-103 — CRM básico das demandas de captação: etapa no quadro, responsável e linha do tempo
 * (notas da equipe e registro automático das mudanças).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandas_captacao', function (Blueprint $table) {
            $table->string('status', 16)->default('nova');
            $table->foreignUuid('responsavel_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestampTz('status_alterado_em')->nullable();
            $table->index('status');
        });
        DB::statement("ALTER TABLE demandas_captacao ADD CONSTRAINT demandas_captacao_status_ck CHECK (status IN ('nova', 'em_analise', 'respondida', 'encerrada'))");

        Schema::create('demanda_captacao_eventos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('demanda_captacao_id')->constrained('demandas_captacao')->cascadeOnDelete();
            $table->foreignUuid('autor_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('tipo', 16);
            $table->text('texto');
            $table->timestampTz('created_at');

            $table->index(['demanda_captacao_id', 'created_at']);
        });
        DB::statement("ALTER TABLE demanda_captacao_eventos ADD CONSTRAINT demanda_captacao_eventos_tipo_ck CHECK (tipo IN ('nota', 'status', 'responsavel'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('demanda_captacao_eventos');
        DB::statement('ALTER TABLE demandas_captacao DROP CONSTRAINT IF EXISTS demandas_captacao_status_ck');
        Schema::table('demandas_captacao', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('responsavel_id');
            $table->dropColumn(['status', 'status_alterado_em']);
        });
    }
};
