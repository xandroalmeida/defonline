<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PDR-020 (2026-11-06) — sai o alerta de trials por IP: IP dinâmico não identifica ninguém. Sem o
 * alerta, o IP da ativação deixa de ter finalidade e também sai (dado pessoal, LGPD).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE trial_config DROP CONSTRAINT IF EXISTS trial_config_limite_ip_ck');

        Schema::table('trial_config', function (Blueprint $table) {
            $table->dropColumn('limite_ip_30d');
        });

        Schema::table('trial_ativacoes', function (Blueprint $table) {
            $table->dropIndex(['ip', 'ativado_em']);
            $table->dropColumn('ip');
        });
    }

    public function down(): void
    {
        Schema::table('trial_ativacoes', function (Blueprint $table) {
            $table->string('ip', 45)->nullable();
            $table->index(['ip', 'ativado_em']);
        });

        Schema::table('trial_config', function (Blueprint $table) {
            $table->unsignedSmallInteger('limite_ip_30d')->default(3);
        });

        DB::statement('ALTER TABLE trial_config ADD CONSTRAINT trial_config_limite_ip_ck CHECK (limite_ip_30d >= 1)');
    }
};
