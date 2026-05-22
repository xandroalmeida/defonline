<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `evento_produto` — append-only, sem PII (ADR-004 §Decisão 2).
 *
 * Captura dos 6 eventos canônicos do north star (`usuario_cadastrado`,
 * `empresa_cadastrada`, `quiz_iniciado`, `diagnostico_concluido`,
 * `diagnostico_visualizado`, `comparativo_aberto`).
 *
 * Validação de PII proibida acontece no `EventLogger::emit()` antes do INSERT
 * (ADR-004 §2.4 — defesa em camadas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_produto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('evento_id')->unique();
            $table->string('nome_evento')->index();
            $table->timestampTz('ocorrido_em')->useCurrent();
            $table->uuid('usuario_id')->nullable()->index();
            $table->uuid('empresa_id')->nullable()->index();
            $table->jsonb('propriedades')->default(DB::raw("'{}'::jsonb"));
            $table->string('request_id')->nullable()->index();
            $table->timestampTz('inserido_em')->useCurrent();

            $table->index(['nome_evento', 'ocorrido_em']);
        });

        DB::statement('CREATE INDEX gin_evento_produto_propriedades ON evento_produto USING gin (propriedades jsonb_path_ops)');

        $appUser = (string) env('DB_USERNAME', 'defonline_app');
        DB::statement("REVOKE UPDATE, DELETE ON evento_produto FROM {$appUser}");
        DB::statement("GRANT INSERT, SELECT ON evento_produto TO {$appUser}");
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_produto');
    }
};
