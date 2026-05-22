<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `audit_logs` — registro jurídico append-only (ADR-003 §Decisão 4).
 *
 * Populado via `AuditLogger::log(...)`. Retenção 5-10 anos (RNF §7.3).
 * GRANT restritos ao role `defonline_app` (ADR-005 §7.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->string('request_id')->nullable()->index();
            $table->string('actor_type', 32)->nullable();
            $table->uuid('actor_id')->nullable();
            $table->uuid('usuario_id')->nullable()->index();
            $table->string('action', 64)->index();
            $table->string('subject_type', 64);
            $table->uuid('subject_id')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->jsonb('context')->nullable();
        });

        // Defesa em profundidade: app só insere/lê (ADR-005 §7.5).
        $appUser = (string) env('DB_USERNAME', 'defonline_app');
        DB::statement("REVOKE UPDATE, DELETE ON audit_logs FROM {$appUser}");
        DB::statement("GRANT INSERT, SELECT ON audit_logs TO {$appUser}");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
