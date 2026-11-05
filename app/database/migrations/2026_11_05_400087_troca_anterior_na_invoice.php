<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-082 × 085 — a Invoice `troca_de_plano` guarda como a assinatura estava ANTES da troca (e os marcadores de
 * como ficou DEPOIS). Estornar a diferença volta ao plano anterior quando o ciclo ainda é o mesmo; sem isso, não
 * há como saber para onde voltar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->jsonb('troca_anterior')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('troca_anterior'));
    }
};
