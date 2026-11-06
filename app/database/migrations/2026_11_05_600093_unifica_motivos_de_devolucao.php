<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * STORY-093 (junção das correções 088 e 092) — as duas migrations trocaram a mesma constraint
 * (`invoices_devolucao_motivo_ck`) e a de nome posterior (088) apagava os motivos da outra (092). Esta, que roda por
 * último, deixa a lista completa: os da 085, `checkout_encerrado` (088) e os três da 092.
 */
return new class extends Migration
{
    private const DA_088 = "'assinatura_cancelada','assinatura_antiga','assinatura_duplicada','checkout_encerrado'";

    public function up(): void
    {
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_devolucao_motivo_ck');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_devolucao_motivo_ck CHECK (devolucao_motivo IS NULL OR devolucao_motivo IN ('.self::DA_088.",'troca_sem_efeito','renovacao_repetida','checkout_cancelado'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE invoices SET devolucao_motivo = 'assinatura_duplicada' WHERE devolucao_motivo IN ('troca_sem_efeito','renovacao_repetida','checkout_cancelado')");
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_devolucao_motivo_ck');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_devolucao_motivo_ck CHECK (devolucao_motivo IS NULL OR devolucao_motivo IN ('.self::DA_088.'))');
    }
};
