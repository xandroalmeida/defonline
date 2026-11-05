<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-081 / ADR-010 Q6 — troca de cartão por novo Checkout recorrente. A assinatura antiga só é
 * cancelada no provedor depois que o cartão novo é confirmado (`antiga_cancelada_em` marca quando),
 * para nunca haver cobrança em dobro nem um intervalo sem cobrança. Sem dado de cartão: só o que o
 * provedor devolve em `assinaturas.cartao_*`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trocas_de_cartao', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignUuid('assinatura_id')->constrained('assinaturas')->restrictOnDelete();
            $table->string('provedor', 20);
            $table->string('status', 12);
            $table->string('checkout_id_externo', 120)->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestampTz('checkout_expira_em')->nullable();
            $table->boolean('cobra_agora')->default(false);
            $table->string('antiga_id_externo', 120);
            $table->string('nova_id_externo', 120)->nullable();
            $table->timestampTz('concluida_em')->nullable();
            $table->timestampTz('antiga_cancelada_em')->nullable();
            $table->timestampsTz();

            $table->index(['assinatura_id', 'status']);
        });

        DB::statement("ALTER TABLE trocas_de_cartao ADD CONSTRAINT trocas_de_cartao_status_ck CHECK (status IN ('pendente','concluida','expirada','cancelada','falhou'))");
        DB::statement('CREATE UNIQUE INDEX trocas_de_cartao_checkout_unico ON trocas_de_cartao (provedor, checkout_id_externo) WHERE checkout_id_externo IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('trocas_de_cartao');
    }
};
