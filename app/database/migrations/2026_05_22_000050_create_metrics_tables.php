<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas de métricas RED técnicas (ADR-004 §1.2).
 *
 * - `request_metrics` — uma linha por request HTTP que entra no `web`.
 * - `job_metrics`     — uma linha por job concluído ou falho no `worker`.
 * - `business_metrics` — eventos técnicos enumerados (motor, PDF, gateway, e-mail).
 *
 * Retenção 90 dias online via job `ExpurgarRequestMetrics` (futuro — STORY pós-007).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('request_id')->index();
            $table->string('path');
            $table->string('method', 10);
            $table->smallInteger('status');
            $table->integer('duration_ms');
            $table->uuid('usuario_id')->nullable();
            $table->uuid('empresa_id')->nullable();
            $table->timestampTz('inserido_em')->useCurrent();

            $table->index(['path', 'status', 'inserido_em'], 'idx_request_metrics_path_status');
        });

        DB::statement('CREATE INDEX brin_request_metrics_inserido_em ON request_metrics USING BRIN (inserido_em)');

        Schema::create('job_metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('request_id')->index();
            $table->string('job_class');
            $table->string('queue', 64);
            $table->string('status', 16);
            $table->integer('duration_ms');
            $table->smallInteger('tentativas')->default(1);
            $table->timestampTz('inserido_em')->useCurrent();
        });

        DB::statement('CREATE INDEX brin_job_metrics_inserido_em ON job_metrics USING BRIN (inserido_em)');

        Schema::create('business_metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('request_id')->index();
            $table->string('tipo')->index();
            $table->boolean('sucesso');
            $table->integer('duracao_ms')->nullable();
            $table->jsonb('meta')->default(DB::raw("'{}'::jsonb"));
            $table->timestampTz('inserido_em')->useCurrent();

            $table->index(['tipo', 'inserido_em'], 'idx_business_metrics_tipo_inserido_em');
        });

        DB::statement('CREATE INDEX brin_business_metrics_inserido_em ON business_metrics USING BRIN (inserido_em)');
    }

    public function down(): void
    {
        Schema::dropIfExists('business_metrics');
        Schema::dropIfExists('job_metrics');
        Schema::dropIfExists('request_metrics');
    }
};
