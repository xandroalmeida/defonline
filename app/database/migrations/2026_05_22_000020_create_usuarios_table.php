<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela `usuarios` — root tenant do modelo de domínio (ADR-003).
 *
 * Phase 1 (STORY-007): apenas a estrutura mínima necessária para os testes do hello world.
 * EPIC-001 (Cadastro) vai adicionar Subscription, TermAcceptance e enriquecer este modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cpf', 11)->unique()->nullable();
            $table->string('nome');
            $table->string('telefone')->nullable();
            $table->string('senha_hash');
            $table->rememberToken();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('anonimizado_at')->nullable();
        });

        // E-mail case-insensitive nativo (ADR-003 §Decisão 7).
        // Laravel não conhece CITEXT — usa SQL direto. Coluna obrigatória + unique.
        DB::statement('ALTER TABLE usuarios ADD COLUMN email CITEXT NOT NULL');
        DB::statement('CREATE UNIQUE INDEX usuarios_email_unique ON usuarios (email)');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuarios');
    }
};
