<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PDR-018 — o NCG absoluto entra na matriz editável, com um texto para NCG positivo e
 * outro para negativo em cada segmento. Origem: Anexo F §F.13, com a redação de
 * Indústria enxugada pelo autor na devolutiva da EBC (STORY-042).
 */
return new class extends Migration
{
    private const FAIXAS = ['positiva' => 'Acima de zero', 'negativa' => 'Zero ou abaixo'];

    /** @var array<string, array<string, string>> */
    private const TEXTOS = [
        'industria' => [
            'positiva' => 'A elevação deve ser acompanhada, na mesma proporção, pelo aumento das fontes de financiamento de longo prazo, preferencialmente de fornecedores.',
            'negativa' => 'Teoricamente, a empresa não necessita de recursos para o giro. NCG negativa significa Investimento Operacional em Giro (IOG).',
        ],
        'comercio' => [
            'positiva' => 'O crescimento das vendas e o aumento do prazo concedido a clientes gera maior NCG e deve ser acompanhado, na mesma proporção, pelo aumento do prazo para pagamento a fornecedores.',
            'negativa' => 'Em tese, a empresa não necessita de recursos para o giro. NCG negativa significa Investimento Operacional em Giro (IOG).',
        ],
        'servicos' => [
            'positiva' => 'A elevação da NCG deve ser acompanhada, na mesma proporção, pelo aumento do prazo para pagamento a fornecedores.',
            'negativa' => 'Em tese, o negócio não necessita de recursos para o giro. NCG negativa significa Investimento Operacional em Giro (IOG).',
        ],
    ];

    public function up(): void
    {
        foreach (self::TEXTOS as $setor => $textos) {
            foreach ($textos as $situacao => $texto) {
                DB::table('recomendacoes')->insert([
                    'id' => (string) Str::uuid7(),
                    'setor' => $setor,
                    'indicador' => 'ncg_absoluto',
                    'farol' => $situacao,
                    'faixa' => self::FAIXAS[$situacao],
                    'texto' => $texto,
                    'created_at' => DB::raw('now()'),
                    'updated_at' => DB::raw('now()'),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('recomendacoes')->where('indicador', 'ncg_absoluto')->delete();
    }
};
