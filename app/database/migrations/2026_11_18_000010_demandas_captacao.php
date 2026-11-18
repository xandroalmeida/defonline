<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * STORY-102 / PDR-021 — demanda de captação (bloco 4, Q17 = sim).
 *
 * `captacao_config` é linha única: o e-mail da equipe que recebe as demandas, editado no backoffice.
 * Vazio, nenhum e-mail sai para a equipe. `demandas_captacao` tem uma linha por diagnóstico com
 * captação e guarda se o cliente autorizou o contato (opt-out: vem marcado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captacao_config', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 255)->nullable();
            $table->uuid('alterado_por_usuario_id')->nullable();
            $table->timestampsTz();
        });

        DB::table('captacao_config')->insert([
            'id' => (string) Str::uuid7(), 'email' => null,
            'created_at' => DB::raw('now()'), 'updated_at' => DB::raw('now()'),
        ]);

        Schema::create('demandas_captacao', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('diagnostico_id')->unique()->constrained('diagnosticos')->cascadeOnDelete();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignUuid('empresa_analisada_id')->constrained('empresas_analisadas')->restrictOnDelete();
            $table->decimal('valor_captar', 15, 2)->nullable();
            $table->boolean('autoriza_contato');
            $table->timestampTz('equipe_avisada_em')->nullable();
            $table->timestampTz('cliente_avisado_em')->nullable();
            $table->timestampsTz();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandas_captacao');
        Schema::dropIfExists('captacao_config');
    }
};
