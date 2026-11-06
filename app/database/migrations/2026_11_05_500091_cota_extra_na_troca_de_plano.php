<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-091 CA-3 — a Invoice `troca_de_plano` guarda a cota extra que a tela de revisão prometeu (proporcional
 * ao que faltava quando o checkout abriu). Quem paga minutos depois recebe o que viu, não o que sobra no relógio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedSmallInteger('cota_extra_troca')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('cota_extra_troca'));
    }
};
