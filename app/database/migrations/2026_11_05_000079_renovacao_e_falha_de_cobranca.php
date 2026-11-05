<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-079 / ADR-010 §3 e §4.2 — renovação e falha de cobrança.
 *
 * - `aviso_renovacao_periodo`: o `periodos_pagos` para o qual o e-mail de D-7 já saiu (idempotência
 *   do aviso, mesmo que a rotina rode de novo);
 * - `atraso_*`, `tentativas_cobranca`, `proxima_tentativa_em`: a régua própria de 3 retentativas a
 *   cada 48 h sobre a MESMA cobrança do provedor (nunca uma cobrança nova, para não duplicar);
 * - `encerramento_pendente_no_provedor`: a recorrência no provedor ainda precisa ser cancelada;
 * - `encerrada_em`: quando o ciclo foi encerrado por falta de pagamento (histórico preservado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->unsignedSmallInteger('aviso_renovacao_periodo')->nullable();
            $table->timestampTz('atraso_desde')->nullable();
            $table->string('atraso_cobranca_id', 120)->nullable();
            $table->unsignedSmallInteger('tentativas_cobranca')->default(0);
            $table->timestampTz('proxima_tentativa_em')->nullable();
            $table->timestampTz('atraso_aviso_em')->nullable();
            $table->boolean('encerramento_pendente_no_provedor')->default(false);
            $table->timestampTz('encerrada_em')->nullable();
        });

        DB::statement("CREATE INDEX assinaturas_regua_idx ON assinaturas (proxima_tentativa_em) WHERE status = 'em_atraso'");
        DB::statement('CREATE INDEX assinaturas_encerramento_pendente_idx ON assinaturas (id) WHERE encerramento_pendente_no_provedor');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS assinaturas_encerramento_pendente_idx');
        DB::statement('DROP INDEX IF EXISTS assinaturas_regua_idx');
        Schema::table('assinaturas', fn (Blueprint $t) => $t->dropColumn([
            'aviso_renovacao_periodo', 'atraso_desde', 'atraso_cobranca_id', 'tentativas_cobranca',
            'proxima_tentativa_em', 'atraso_aviso_em', 'encerramento_pendente_no_provedor', 'encerrada_em',
        ]));
    }
};
