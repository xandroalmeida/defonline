<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-092 (D-08) — pagamento confirmado que não tem o que conceder entra na lista de devolução com um motivo:
 * a diferença de um upgrade paga depois de a base mudar (`troca_sem_efeito`), o segundo QR Code da renovação anual
 * (`renovacao_repetida`) e o checkout já cancelado que o provedor confirma como pago (`checkout_cancelado`).
 *
 * A Invoice da diferença de upgrade guarda de que plano e ciclo ela foi calculada (`plano_origem_id`,
 * `ciclo_origem`): pagar duas diferenças da mesma base aplica a primeira e devolve a segunda.
 */
return new class extends Migration
{
    private const ANTIGOS = "'assinatura_cancelada','assinatura_antiga','assinatura_duplicada'";

    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('plano_origem_id')->nullable();
            $table->string('ciclo_origem', 10)->nullable();
        });

        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_devolucao_motivo_ck');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_devolucao_motivo_ck CHECK (devolucao_motivo IS NULL OR devolucao_motivo IN ('.self::ANTIGOS.",'troca_sem_efeito','renovacao_repetida','checkout_cancelado'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE invoices SET devolucao_motivo = 'assinatura_duplicada' WHERE devolucao_motivo IN ('troca_sem_efeito','renovacao_repetida','checkout_cancelado')");
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_devolucao_motivo_ck');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_devolucao_motivo_ck CHECK (devolucao_motivo IS NULL OR devolucao_motivo IN ('.self::ANTIGOS.'))');
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['plano_origem_id', 'ciclo_origem']));
    }
};
