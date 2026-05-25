<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory de teste para {@see Diagnostico}.
 *
 * Padrão IDR-010 §sub-decisão 2: snapshot imutável. A factory popula um
 * payload genérico (`quiz_payload`), versão de motor/matriz default e
 * estruturas mínimas válidas em `indicadores_calculados`/`resumo_executivo`.
 * Testes do motor real (`tests/Domain/Motor`) sobrescrevem via state — esta
 * factory existe para Feature/Arch tests que só precisam de um registro
 * persistido (ex.: smoke do Global Scope, IDR-009 cross-tenant 404).
 *
 * `usuario_id` é resolvido antes de `empresa_analisada_id`; o closure abaixo
 * cria a `EmpresaAnalisada` **dentro do mesmo tenant** para manter consistência
 * com o Global Scope (`BelongsToUsuarioScope`).
 *
 * @extends Factory<Diagnostico>
 */
final class DiagnosticoFactory extends Factory
{
    /** @var class-string<Diagnostico> */
    protected $model = Diagnostico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payload = self::quizPayloadIndustriaPadrao();
        $canonicalJson = (string) json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );

        return [
            'id' => (string) Str::uuid7(),
            'usuario_id' => Usuario::factory(),
            'empresa_analisada_id' => fn (array $attrs) => EmpresaAnalisada::factory()
                ->state(['usuario_id' => $attrs['usuario_id']])
                ->create()->id,
            'motor_version' => '1.0.0',
            'matrix_version' => 'dez-2025',
            'setor' => 'industria',
            'quiz_payload' => $payload,
            'payload_hash' => hash('sha256', $canonicalJson),
            'indicadores_calculados' => self::indicadoresStubVerdes(),
            'resumo_executivo' => [
                'veredito' => 'saudavel',
                'destaques_negativos' => [],
                'destaque_positivo' => null,
                'fallback_acionado' => false,
            ],
            'gerado_em' => now(),
        ];
    }

    /**
     * Vincula o diagnóstico a uma {@see EmpresaAnalisada} já existente, mantendo
     * o `usuario_id` alinhado com o do tenant da empresa.
     */
    public function paraEmpresa(EmpresaAnalisada $empresa): self
    {
        return $this->state(fn () => [
            'usuario_id' => $empresa->usuario_id,
            'empresa_analisada_id' => $empresa->id,
        ]);
    }

    /**
     * Payload de quiz canônico padrão (Indústria, perfil saudável genérico).
     * Estruturado segundo Anexo A da spec — chaves Q01..Q23, decimais em string,
     * compatível com o canonicalizador de IDR-010.
     *
     * @return array<string, mixed>
     */
    public static function quizPayloadIndustriaPadrao(): array
    {
        return [
            'Q01' => 1,            // Indústria
            'Q02' => '50000.00',   // Disponibilidades
            'Q03' => '80000.00',   // Clientes (contas a receber)
            'Q04' => '60000.00',   // Estoques
            'Q05' => '300000.00',  // Imobilizado / patrimônio
            'Q06' => '40000.00',   // Dívidas financeiras
            'Q07' => '30000.00',   // Fornecedores
            'Q08' => '50000.00',   // Compras médias mensais
            'Q09' => '100000.00',  // Vendas médias mensais
            'Q10' => 45,           // PMC (dias)
            'Q11' => 30,           // PME (dias)
            'Q12' => 45,           // PMR (dias)
            'Q13' => '2.00',       // Inadimplência (%)
            'Q14' => '20000.00',   // Despesas fixas mensais
            'Q15' => '8000.00',    // Despesas variáveis mensais
            'Q16' => '2000.00',    // Despesas financeiras mensais
            'Q17' => 2,            // Não precisa captar
            'Q18' => null,
            'Q19' => null,
            'Q20' => null,
            'Q21' => null,
            'Q22' => null,
            'Q23' => null,
        ];
    }

    /**
     * Stub mínimo do bloco `indicadores_calculados` — válido sintaticamente
     * mas não derivado do motor real (testes do motor sobrescrevem).
     *
     * @return array<string, array<string, mixed>>
     */
    private static function indicadoresStubVerdes(): array
    {
        $verde = ['valor' => 0.0, 'farol' => 'verde', 'motivo' => null, 'mensagem' => 'Faixa verde'];

        return [
            'margem_bruta' => $verde,
            'margem_liquida' => $verde,
            'divida_liquida_ebitda' => $verde,
            'ncg_vendas' => $verde,
            'pmr' => $verde,
            'pmc' => $verde,
            'ciclo_financeiro' => $verde,
            'ncg_absoluto' => [
                'valor' => -10000.0,
                'farol' => 'nenhum',
                'motivo' => null,
                'mensagem' => 'Folga operacional.',
            ],
        ];
    }
}
