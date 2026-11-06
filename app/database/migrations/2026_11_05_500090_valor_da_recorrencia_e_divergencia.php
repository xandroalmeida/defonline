<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-090 — duas marcas:
 *
 * - `trocas_de_cartao.valor_centavos` e `ciclo`: o valor e o ciclo com que o checkout novo foi aberto. Na
 *   confirmação, se a assinatura mudou de valor ou de ciclo nesse meio tempo (um upgrade), o provedor é corrigido.
 * - `invoices.valor_esperado_centavos`: a renovação cobrada por um valor diferente do contratado guarda o que
 *   se esperava, para o Financeiro mostrar a divergência (alerta da equipe junto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trocas_de_cartao', function (Blueprint $table) {
            $table->unsignedInteger('valor_centavos')->nullable();
            $table->string('ciclo', 10)->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('valor_esperado_centavos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('valor_esperado_centavos'));
        Schema::table('trocas_de_cartao', fn (Blueprint $t) => $t->dropColumn(['valor_centavos', 'ciclo']));
    }
};
