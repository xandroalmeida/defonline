<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDR-017 — nome fantasia informado pelo usuário em empresa com CNPJ.
 *
 * Fica em coluna própria porque `nome_fantasia` guarda o valor da Receita, que
 * segue imutável (PDR-010). Null quando o usuário não informou outro nome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas_analisadas', function (Blueprint $table) {
            $table->text('nome_fantasia_informado')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empresas_analisadas', function (Blueprint $table) {
            $table->dropColumn('nome_fantasia_informado');
        });
    }
};
