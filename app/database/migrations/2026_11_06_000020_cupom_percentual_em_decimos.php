<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PO, 2026-11-06 — percentual do cupom com uma casa decimal. `cupons.valor` passa a guardar décimos
 * de ponto percentual (20,5% = 205), inteiro como os centavos do valor fixo. Só `cupons` muda: os
 * descontos já aplicados estão gravados em centavos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE cupons DROP CONSTRAINT cupons_valor_check');
        DB::statement("UPDATE cupons SET valor = valor * 10 WHERE tipo = 'percentual'");
        DB::statement("ALTER TABLE cupons ADD CONSTRAINT cupons_valor_check CHECK (valor >= 1 AND (tipo <> 'percentual' OR valor <= 1000))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cupons DROP CONSTRAINT cupons_valor_check');
        DB::statement("UPDATE cupons SET valor = GREATEST(1, ROUND(valor / 10.0)) WHERE tipo = 'percentual'");
        DB::statement("ALTER TABLE cupons ADD CONSTRAINT cupons_valor_check CHECK (valor >= 1 AND (tipo <> 'percentual' OR valor <= 100))");
    }
};
