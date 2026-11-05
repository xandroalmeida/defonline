<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-080 / ADR-010 — plano anual pago com Pix: um Checkout Pix avulso por ano, sem assinatura no
 * provedor. `meio_pagamento` distingue a assinatura que não renova sozinha; `aviso_renovacao_pix_para`
 * guarda o vencimento para o qual o aviso de renovação já saiu (um aviso por ciclo, mesmo que a rotina
 * rode de novo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->string('meio_pagamento', 10)->default('cartao');
            $table->timestampTz('aviso_renovacao_pix_para')->nullable();
        });

        DB::statement("ALTER TABLE assinaturas ADD CONSTRAINT assinaturas_meio_pagamento_ck CHECK (meio_pagamento IN ('cartao','pix'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE assinaturas DROP CONSTRAINT assinaturas_meio_pagamento_ck');
        Schema::table('assinaturas', fn (Blueprint $t) => $t->dropColumn(['meio_pagamento', 'aviso_renovacao_pix_para']));
    }
};
