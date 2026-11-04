<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-066 CA-6 / PDR-012 — o admin que fez a última gravação pelo "Logar como".
 * Em `request_metrics` é o admin da requisição (sem FK, como `usuario_id` ali).
 */
return new class extends Migration
{
    private const TABELAS_DE_DOMINIO = ['empresas_analisadas', 'diagnosticos', 'quiz_rascunhos'];

    public function up(): void
    {
        foreach (self::TABELAS_DE_DOMINIO as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->foreignUuid('personificado_por_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            });
        }

        Schema::table('request_metrics', function (Blueprint $table) {
            $table->uuid('personificado_por_id')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([...self::TABELAS_DE_DOMINIO, 'request_metrics'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('personificado_por_id');
            });
        }
    }
};
