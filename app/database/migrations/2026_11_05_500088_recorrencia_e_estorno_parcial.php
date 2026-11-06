<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-088 (V087 D-01, D-03, D-16).
 *
 * - `assinatura_id_externo`: a recorrência do provedor que gerou o pagamento duplicado. A cobrança do mês seguinte
 *   dessa recorrência não tem assinatura nossa; é por aqui que se acha o dono do dinheiro a devolver.
 * - `reembolsado_parcial_centavos`: o estorno parcial só registra o valor; a cobrança segue paga e o acesso não muda.
 * - `provider_events.valor_estornado_centavos`: o valor do estorno parcial, já em centavos pelo adaptador.
 * - Novo motivo de devolução `checkout_encerrado`: pagamento confirmado de um checkout que já estava cancelado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('assinatura_id_externo', 100)->nullable();
            $table->unsignedInteger('reembolsado_parcial_centavos')->nullable();
            $table->index(['provedor', 'assinatura_id_externo'], 'invoices_provedor_assinatura_externa');
        });

        Schema::table('provider_events', function (Blueprint $table) {
            $table->unsignedInteger('valor_estornado_centavos')->nullable();
        });

        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_devolucao_motivo_ck');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_devolucao_motivo_ck CHECK (devolucao_motivo IS NULL OR devolucao_motivo IN ('assinatura_cancelada','assinatura_antiga','assinatura_duplicada','checkout_encerrado'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE invoices SET devolucao_motivo = 'assinatura_cancelada' WHERE devolucao_motivo = 'checkout_encerrado'");
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_devolucao_motivo_ck');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_devolucao_motivo_ck CHECK (devolucao_motivo IS NULL OR devolucao_motivo IN ('assinatura_cancelada','assinatura_antiga','assinatura_duplicada'))");

        Schema::table('provider_events', function (Blueprint $table) {
            $table->dropColumn('valor_estornado_centavos');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_provedor_assinatura_externa');
            $table->dropColumn(['assinatura_id_externo', 'reembolsado_parcial_centavos']);
        });
    }
};
