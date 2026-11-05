<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PDR-014 — o NCG/Vendas é razão direta com limite 10,00, não 10%. Só o rótulo da
 * faixa no backoffice muda: os textos, que podem ter sido editados, ficam como estão.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->trocarFaixas('0,01–10,00', 'Acima de 10,00');
    }

    public function down(): void
    {
        $this->trocarFaixas('0,01–10%', 'Acima de 10%');
    }

    private function trocarFaixas(string $amarelo, string $vermelho): void
    {
        foreach (['amarelo' => $amarelo, 'vermelho' => $vermelho] as $farol => $faixa) {
            DB::table('recomendacoes')
                ->where('indicador', 'ncg_vendas')
                ->where('farol', $farol)
                ->update(['faixa' => $faixa]);
        }
    }
};
