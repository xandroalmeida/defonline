<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-056 CA-8 — eventos idempotentes. `chave_idempotencia` identifica o FATO que o evento registra
 * (o lançamento fechado, o bloco concluído de um rascunho, a exportação de uma requisição); o índice
 * único parcial faz o banco recusar o segundo registro do mesmo fato, mesmo em corrida. Evento sem
 * chave (os da STORY-035) segue como antes: nada muda para quem não pede idempotência.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evento_produto', function (Blueprint $table) {
            $table->string('chave_idempotencia', 160)->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX evento_produto_idempotencia_unique ON evento_produto (nome_evento, chave_idempotencia) WHERE chave_idempotencia IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS evento_produto_idempotencia_unique');
        Schema::table('evento_produto', function (Blueprint $table) {
            $table->dropColumn('chave_idempotencia');
        });
    }
};
