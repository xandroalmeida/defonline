<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-063 (PDR-010) — ficha completa da RFB e observações do usuário.
 *
 * - `ficha_rfb` (jsonb): CNAEs secundários, endereço, sócios, regime
 *   MEI/Simples etc., exatamente como a consulta devolveu. Só é escrita por
 *   uma consulta à Receita; nunca pelo usuário. Null para CPF e para empresas
 *   cadastradas antes desta estória (legado — STORY-064 atualiza).
 * - `observacoes` (text): única informação livre do usuário sobre a empresa.
 *
 * Os campos-núcleo (razao_social, cnae, municipio…) continuam em colunas
 * próprias porque o resto do sistema (listagem, quiz, relatório) já lê deles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas_analisadas', function (Blueprint $table) {
            $table->jsonb('ficha_rfb')->nullable();
            $table->text('observacoes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empresas_analisadas', function (Blueprint $table) {
            $table->dropColumn(['ficha_rfb', 'observacoes']);
        });
    }
};
