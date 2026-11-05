<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDR-019 — e-mail e telefone/WhatsApp da empresa, opcionais e informados pelo usuário.
 * O telefone fica só com dígitos (DDD + número).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas_analisadas', function (Blueprint $table) {
            $table->string('email', 255)->nullable();
            $table->string('telefone', 11)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empresas_analisadas', function (Blueprint $table) {
            $table->dropColumn(['email', 'telefone']);
        });
    }
};
