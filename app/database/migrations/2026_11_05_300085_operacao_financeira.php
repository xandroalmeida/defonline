<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-085 / IDR-027 — operação financeira no backoffice: reembolso e nota fiscal por cobrança.
 *
 * - `reembolso_estado` é a trava do "não reembolsar duas vezes": só vira não nulo sob o lock da linha e
 *   enquanto não for devolvido ao nulo por uma recusa/falha comprovada do provedor;
 * - `devolucao_motivo` marca o pagamento que entrou mas não concedeu nada (assinatura cancelada, trocada
 *   ou duplicada): a tela o destaca para o admin devolver;
 * - `nf_*`: a emissão da nota é fora do código (espec §2.4); aqui só o controle de quem marcou e quando;
 * - o lote estornado ganha um estado próprio, para não se confundir com esgotado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('reembolso_estado', 24)->nullable();
            $table->text('reembolso_motivo')->nullable();
            $table->uuid('reembolso_por_id')->nullable();
            $table->timestampTz('reembolso_solicitado_em')->nullable();
            $table->timestampTz('reembolsado_em')->nullable();
            $table->string('devolucao_motivo', 30)->nullable();
            $table->timestampTz('nf_emitida_em')->nullable();
            $table->uuid('nf_emitida_por_id')->nullable();
            $table->string('nf_numero', 40)->nullable();
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_reembolso_estado_ck CHECK (reembolso_estado IS NULL OR reembolso_estado IN ('em_andamento','aguardando_autorizacao','concluido'))");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_devolucao_motivo_ck CHECK (devolucao_motivo IS NULL OR devolucao_motivo IN ('assinatura_cancelada','assinatura_antiga','assinatura_duplicada'))");
        DB::statement('CREATE INDEX invoices_status_pago_em ON invoices (status, pago_em)');

        DB::statement('ALTER TABLE lotes_credito DROP CONSTRAINT lotes_credito_status_ck');
        DB::statement("ALTER TABLE lotes_credito ADD CONSTRAINT lotes_credito_status_ck CHECK (status IN ('ativo','esgotado','expirado','estornado'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE lotes_credito SET status = 'esgotado' WHERE status = 'estornado'");
        DB::statement('ALTER TABLE lotes_credito DROP CONSTRAINT lotes_credito_status_ck');
        DB::statement("ALTER TABLE lotes_credito ADD CONSTRAINT lotes_credito_status_ck CHECK (status IN ('ativo','esgotado','expirado'))");
        DB::statement('DROP INDEX IF EXISTS invoices_status_pago_em');
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn([
            'reembolso_estado', 'reembolso_motivo', 'reembolso_por_id', 'reembolso_solicitado_em', 'reembolsado_em',
            'devolucao_motivo', 'nf_emitida_em', 'nf_emitida_por_id', 'nf_numero',
        ]));
    }
};
