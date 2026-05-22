<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `term_acceptances` — registro append-only de aceite (ou recusa) dos termos
 * (Termo de Adesão, LGPD, marketing) por Usuário no momento do cadastro
 * (STORY-012 CA-3). LGPD exige evidência granular, datada, com hash do conteúdo
 * exato exibido para reconstituir o que o Usuário aceitou caso o texto mude.
 *
 * `ip`/`user_agent` ficam SÓ aqui — não vão para `audit_logs` nem para
 * `evento_produto` (decisão LGPD: PII minimizada em logs).
 *
 * Append-only: REVOKE UPDATE/DELETE no role da aplicação (mesma defesa em
 * camadas de `audit_logs` e `evento_produto`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_acceptances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('termo_tipo', 32);
            $table->boolean('aceito');
            $table->string('versao', 32);
            $table->string('conteudo_hash', 64);
            $table->ipAddress('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('aceito_at')->useCurrent();

            $table->index(['usuario_id', 'termo_tipo', 'aceito_at'], 'idx_term_acceptances_usuario_tipo_data');
        });

        DB::statement(
            "ALTER TABLE term_acceptances ADD CONSTRAINT term_acceptances_termo_tipo_check
                CHECK (termo_tipo IN ('termo_adesao', 'lgpd', 'marketing'))",
        );

        $appUser = (string) config('database.connections.pgsql.username', 'defonline_app');
        DB::statement("REVOKE UPDATE, DELETE ON term_acceptances FROM {$appUser}");
        DB::statement("GRANT INSERT, SELECT ON term_acceptances TO {$appUser}");
    }

    public function down(): void
    {
        Schema::dropIfExists('term_acceptances');
    }
};
