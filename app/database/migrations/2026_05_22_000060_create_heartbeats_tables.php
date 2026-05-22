<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Heartbeats de worker e scheduler (ADR-004 §1.4).
 *
 * Usados pelo alertador (`php artisan alertas:avaliar`) para detectar processo travado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('hostname');
            $table->string('queue', 64)->nullable();
            $table->timestampTz('ultimo_em')->useCurrent();

            $table->unique(['hostname', 'queue']);
        });

        Schema::create('scheduler_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('task');
            $table->timestampTz('ultimo_em')->useCurrent();

            $table->unique('task');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_heartbeats');
        Schema::dropIfExists('worker_heartbeats');
    }
};
