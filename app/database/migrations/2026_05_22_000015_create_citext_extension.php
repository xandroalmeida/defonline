<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extensão `citext` — tipo do `usuarios.email` (case-insensitive + unique).
 *
 * Até a ADR-009 a extensão vinha do initdb do Postgres em container (IDR-001). No
 * ZalkVPS o banco é o do host e a role `defonline_prod` é dona da base: extensão
 * trusted ela mesma cria, sem superuser. `IF NOT EXISTS` deixa o local (onde o
 * initdb já criou) e o `defonline_test` intocados.
 *
 * O timestamp antecede `create_usuarios_table` de propósito: tem de rodar antes dela
 * em banco novo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS citext');
    }

    public function down(): void
    {
        // Sem DROP: a extensão é pré-requisito de colunas existentes e, no local,
        // pertence ao initdb (superuser), não a esta migration.
    }
};
