<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-074 — controles do trial: quem já ativou (por CPF), quais CNPJs já foram analisados em
 * teste (por conta), o limite de ativações por IP e as marcas de e-mail D-2 e D-0.
 *
 * CPF e CNPJ ficam como HMAC (nunca em claro): o registro precisa sobreviver à exclusão da conta
 * (por isso `usuario_id` aceita nulo) e não pode virar uma lista de documentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_config', function (Blueprint $table) {
            $table->unsignedSmallInteger('limite_ip_30d')->default(3);
        });

        DB::statement('ALTER TABLE trial_config ADD CONSTRAINT trial_config_limite_ip_ck CHECK (limite_ip_30d >= 1)');

        Schema::create('trial_ativacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->char('cpf_hash', 64)->unique();
            $table->string('ip', 45)->nullable();
            $table->timestampTz('ativado_em');
            $table->timestampsTz();

            $table->index(['ip', 'ativado_em']);
        });

        Schema::create('trial_empresas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('cnpj_hash', 64)->unique();
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::table('assinaturas', function (Blueprint $table) {
            $table->timestampTz('aviso_d2_em')->nullable();
            $table->timestampTz('aviso_d0_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->dropColumn(['aviso_d2_em', 'aviso_d0_em']);
        });
        Schema::dropIfExists('trial_empresas');
        Schema::dropIfExists('trial_ativacoes');
        DB::statement('ALTER TABLE trial_config DROP CONSTRAINT IF EXISTS trial_config_limite_ip_ck');
        Schema::table('trial_config', function (Blueprint $table) {
            $table->dropColumn('limite_ip_30d');
        });
    }
};
