<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-077 / ADR-010 — assinatura com cartão recorrente no checkout hospedado.
 *
 * Nada de número, validade ou CVV: só bandeira e 4 últimos dígitos, se o provedor informar.
 * `periodos_pagos` ancora o vencimento em `data_inicio` (sem deriva de fim de mês) e
 * `proxima_cota_em` agenda a renovação mensal da cota no ciclo anual.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_tipo_ck');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_tipo_ck CHECK (tipo IN ('credito','assinatura'))");

        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('plano_id')->nullable();
            $table->string('ciclo', 10)->nullable();
            $table->unsignedInteger('cota_snapshot')->nullable();
            $table->uuid('assinatura_id')->nullable();
            $table->boolean('renovacao')->default(false);
            $table->string('cartao_bandeira', 20)->nullable();
            $table->string('cartao_ultimos4', 4)->nullable();
        });

        Schema::table('assinaturas', function (Blueprint $table) {
            $table->string('provedor', 20)->nullable();
            $table->string('id_externo', 120)->nullable();
            $table->unsignedInteger('preco_snapshot_centavos')->nullable();
            $table->unsignedSmallInteger('periodos_pagos')->default(1);
            $table->timestampTz('proxima_cota_em')->nullable();
            $table->string('cartao_bandeira', 20)->nullable();
            $table->string('cartao_ultimos4', 4)->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX assinaturas_id_externo_unico ON assinaturas (provedor, id_externo) WHERE id_externo IS NOT NULL');

        Schema::table('provider_events', function (Blueprint $table) {
            $table->string('assinatura_id_externo', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('provider_events', fn (Blueprint $t) => $t->dropColumn('assinatura_id_externo'));
        DB::statement('DROP INDEX IF EXISTS assinaturas_id_externo_unico');
        Schema::table('assinaturas', fn (Blueprint $t) => $t->dropColumn(['provedor', 'id_externo', 'preco_snapshot_centavos', 'periodos_pagos', 'proxima_cota_em', 'cartao_bandeira', 'cartao_ultimos4']));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['plano_id', 'ciclo', 'cota_snapshot', 'assinatura_id', 'renovacao', 'cartao_bandeira', 'cartao_ultimos4']));
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_tipo_ck');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_tipo_ck CHECK (tipo IN ('credito'))");
    }
};
