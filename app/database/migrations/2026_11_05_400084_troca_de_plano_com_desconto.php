<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-082 × 083 — o downgrade agendado precisa saber, no momento de valer (na renovação), se o desconto de
 * cupom da assinatura continua no plano novo: `troca_preco_cheio_centavos` é o preço cheio do plano novo e
 * `troca_desconto_centavos` o desconto por ciclo que continua (0 = o desconto termina na troca). `troca_preco_centavos`
 * segue sendo o que o provedor vai cobrar na renovação (já com o desconto, se continua).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->unsignedInteger('troca_preco_cheio_centavos')->nullable();
            $table->unsignedInteger('troca_desconto_centavos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assinaturas', fn (Blueprint $t) => $t->dropColumn(['troca_preco_cheio_centavos', 'troca_desconto_centavos']));
    }
};
