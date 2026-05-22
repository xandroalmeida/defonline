<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-013 CA-1 — adiciona `email_confirmed_at` na tabela `usuarios`.
 *
 * Cadastros novos entram com NULL (conta inativa). Backfill não é exigido —
 * só houve cadastros locais de teste até aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->timestampTz('email_confirmed_at')->nullable()->after('senha_hash');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropColumn('email_confirmed_at');
        });
    }
};
