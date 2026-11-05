<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * STORY-071 / PDR-020 — catálogo de cobrança em tabelas, mantido no backoffice. Os
 * valores abaixo são só a carga inicial: depois dela, quem manda é a tela.
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: string, 2: int, 3: int, 4: int}> código, nome, cota mensal, preço mensal, preço anual (centavos) */
    private const PLANOS = [
        ['basico', 'Básico', 1, 4990, 39900],
        ['pro', 'Pro', 10, 19900, 159900],
    ];

    /** @var list<array{0: string, 1: string, 2: int, 3: int}> código, nome, créditos, preço (centavos) */
    private const PACOTES = [
        ['mini', 'Mini', 1, 8990],
        ['plus', 'Plus', 5, 34990],
        ['max', 'Max', 10, 59990],
    ];

    public function up(): void
    {
        Schema::create('planos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 60)->unique();
            $table->string('nome', 60);
            $table->unsignedInteger('cota_mensal');
            $table->unsignedInteger('preco_mensal_centavos');
            $table->unsignedInteger('preco_anual_centavos');
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('ordem');
            $table->uuid('alterado_por_usuario_id')->nullable();
            $table->timestampsTz();

            $table->index(['ativo', 'ordem']);
        });

        Schema::create('pacotes_credito', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 60)->unique();
            $table->string('nome', 60);
            $table->unsignedInteger('quantidade_creditos');
            $table->unsignedInteger('preco_centavos');
            $table->unsignedInteger('validade_meses');
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('ordem');
            $table->uuid('alterado_por_usuario_id')->nullable();
            $table->timestampsTz();

            $table->index(['ativo', 'ordem']);
        });

        // Uma linha só: o trial é uma configuração, não um item de catálogo.
        Schema::create('trial_config', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('dias');
            $table->unsignedInteger('analises');
            $table->uuid('alterado_por_usuario_id')->nullable();
            $table->timestampsTz();
        });

        foreach (self::PLANOS as $i => [$codigo, $nome, $cota, $mensal, $anual]) {
            DB::table('planos')->insert([
                'id' => (string) Str::uuid7(), 'codigo' => $codigo, 'nome' => $nome, 'cota_mensal' => $cota,
                'preco_mensal_centavos' => $mensal, 'preco_anual_centavos' => $anual, 'ativo' => true,
                'ordem' => $i + 1, 'created_at' => DB::raw('now()'), 'updated_at' => DB::raw('now()'),
            ]);
        }

        foreach (self::PACOTES as $i => [$codigo, $nome, $creditos, $preco]) {
            DB::table('pacotes_credito')->insert([
                'id' => (string) Str::uuid7(), 'codigo' => $codigo, 'nome' => $nome, 'quantidade_creditos' => $creditos,
                'preco_centavos' => $preco, 'validade_meses' => 12, 'ativo' => true,
                'ordem' => $i + 1, 'created_at' => DB::raw('now()'), 'updated_at' => DB::raw('now()'),
            ]);
        }

        DB::table('trial_config')->insert([
            'id' => (string) Str::uuid7(), 'ativo' => true, 'dias' => 7, 'analises' => 1,
            'created_at' => DB::raw('now()'), 'updated_at' => DB::raw('now()'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_config');
        Schema::dropIfExists('pacotes_credito');
        Schema::dropIfExists('planos');
    }
};
