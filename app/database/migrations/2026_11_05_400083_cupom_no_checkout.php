<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-083 / PDR-020 / ADR-010 4.7 — cupom no checkout.
 *
 * - `invoices` guarda o retrato do desconto (preço cheio, desconto, cupom e ciclos) e passa a aceitar a
 *   contratação sem cobrança: valor zero, meio `nenhum`, sempre com desconto. A Invoice pendente com
 *   cupom é a "reserva" da vaga do limite de usos (CA-4); o uso só é gravado quando a contratação conclui.
 * - `assinaturas` guarda o desconto contratado (CA-9: desativar o cupom não o altera), por quantos
 *   ciclos vale, o aviso de fim do desconto (CA-8) e o ajuste de valor pendente no provedor (CA-7).
 *   `meio_pagamento = nenhum` é a assinatura ativada sem cobrança: sem cartão cadastrado.
 * - `cupom_usos` aponta para a Invoice que o consumiu (uma Invoice consome no máximo um cupom).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('cupom_id')->nullable();
            $table->unsignedInteger('cupom_ciclos')->nullable();
            $table->unsignedInteger('preco_cheio_centavos')->nullable();
            $table->unsignedInteger('desconto_centavos')->default(0);

            $table->foreign('cupom_id')->references('id')->on('cupons')->nullOnDelete();
            $table->index(['cupom_id', 'status']);
        });

        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_valor_ck');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_valor_ck CHECK (valor_centavos > 0 OR (valor_centavos = 0 AND meio = 'nenhum' AND desconto_centavos > 0))");

        Schema::table('assinaturas', function (Blueprint $table) {
            $table->uuid('cupom_id')->nullable();
            $table->unsignedInteger('preco_cheio_centavos')->nullable();
            $table->unsignedInteger('desconto_centavos')->default(0);
            // Quantos ciclos (contados desde o 1º da assinatura) têm desconto; nulo = todos.
            $table->unsignedSmallInteger('desconto_periodos')->nullable();
            $table->timestampTz('desconto_aviso_em')->nullable();
            // O desconto acabou e o valor no provedor ainda precisa voltar ao cheio (CA-7).
            $table->boolean('valor_pendente_no_provedor')->default(false);

            $table->foreign('cupom_id')->references('id')->on('cupons')->nullOnDelete();
        });

        DB::statement('ALTER TABLE assinaturas DROP CONSTRAINT assinaturas_meio_pagamento_ck');
        DB::statement("ALTER TABLE assinaturas ADD CONSTRAINT assinaturas_meio_pagamento_ck CHECK (meio_pagamento IN ('cartao','pix','nenhum'))");

        Schema::table('cupom_usos', function (Blueprint $table) {
            $table->uuid('invoice_id')->nullable();

            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });

        // Cadastrar o cartão de uma assinatura ativada sem cobrança: não há assinatura antiga no provedor.
        DB::statement('ALTER TABLE trocas_de_cartao ALTER COLUMN antiga_id_externo DROP NOT NULL');

        DB::statement('CREATE UNIQUE INDEX cupom_usos_invoice_unico ON cupom_usos (invoice_id) WHERE invoice_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM trocas_de_cartao WHERE antiga_id_externo IS NULL');
        DB::statement('ALTER TABLE trocas_de_cartao ALTER COLUMN antiga_id_externo SET NOT NULL');
        DB::statement('DROP INDEX IF EXISTS cupom_usos_invoice_unico');
        Schema::table('cupom_usos', fn (Blueprint $t) => $t->dropColumn('invoice_id'));

        DB::statement("UPDATE assinaturas SET meio_pagamento = 'cartao' WHERE meio_pagamento = 'nenhum'");
        DB::statement('ALTER TABLE assinaturas DROP CONSTRAINT assinaturas_meio_pagamento_ck');
        DB::statement("ALTER TABLE assinaturas ADD CONSTRAINT assinaturas_meio_pagamento_ck CHECK (meio_pagamento IN ('cartao','pix'))");
        Schema::table('assinaturas', function (Blueprint $t) {
            $t->dropForeign(['cupom_id']);
            $t->dropColumn(['cupom_id', 'preco_cheio_centavos', 'desconto_centavos', 'desconto_periodos', 'desconto_aviso_em', 'valor_pendente_no_provedor']);
        });

        DB::statement('DELETE FROM invoices WHERE valor_centavos = 0');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_valor_ck');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_valor_ck CHECK (valor_centavos > 0)');
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropForeign(['cupom_id']);
            $t->dropIndex(['cupom_id', 'status']);
            $t->dropColumn(['cupom_id', 'cupom_ciclos', 'preco_cheio_centavos', 'desconto_centavos']);
        });
    }
};
