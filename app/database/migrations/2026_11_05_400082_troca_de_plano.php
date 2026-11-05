<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-082 / ADR-010 — troca de plano. A diferença do upgrade é uma Invoice avulsa (`troca_de_plano`);
 * o downgrade fica agendado na própria assinatura (`troca_*`) e vale na próxima renovação. O ajuste do
 * valor no provedor é repetível (`ajuste_provedor_pendente`), como o cancelamento da assinatura antiga.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_tipo_ck');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_tipo_ck CHECK (tipo IN ('credito','assinatura','troca_de_plano'))");

        Schema::table('invoices', function (Blueprint $table) {
            // `periodos_pagos` da assinatura quando o checkout da diferença foi aberto: pagar depois de o ciclo mudar não aplica nada.
            $table->unsignedSmallInteger('periodo_base')->nullable();
        });

        Schema::table('assinaturas', function (Blueprint $table) {
            $table->uuid('troca_plano_id')->nullable();
            $table->string('troca_ciclo', 10)->nullable();
            $table->unsignedInteger('troca_preco_centavos')->nullable();
            $table->timestampTz('troca_agendada_em')->nullable();
            $table->boolean('ajuste_provedor_pendente')->default(false);
        });

        DB::statement('ALTER TABLE assinaturas ADD CONSTRAINT assinaturas_troca_ck CHECK ((troca_plano_id IS NULL) = (troca_ciclo IS NULL) AND (troca_plano_id IS NULL) = (troca_preco_centavos IS NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE assinaturas DROP CONSTRAINT assinaturas_troca_ck');
        Schema::table('assinaturas', fn (Blueprint $t) => $t->dropColumn(['troca_plano_id', 'troca_ciclo', 'troca_preco_centavos', 'troca_agendada_em', 'ajuste_provedor_pendente']));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('periodo_base'));
        DB::statement('DELETE FROM invoices WHERE tipo = \'troca_de_plano\'');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_tipo_ck');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_tipo_ck CHECK (tipo IN ('credito','assinatura'))");
    }
};
