<?php

declare(strict_types=1);

namespace App\Domain\Motor;

use Illuminate\Support\Facades\Log;

/**
 * Leitura da matriz de recomendações por indicador × farol (snapshot — STORY-032).
 *
 * **Fonte.** {@see FonteRecomendacoes}: em produção, a tabela `recomendacoes`, mantida
 * no backoffice (PDR-016). Os 123 textos existem, mas o motor só calcula Indústria;
 * Comércio/Serviços entram no EPIC-006.
 *
 * **Contrato.** {@see self::texto()} retorna o texto literal do par
 * `(codigo, farol)`; quando o par não existe (lacuna editorial), devolve
 * `"Recomendação em revisão."` + `Log::warning` + auto-append idempotente em
 * `design/matriz-lacunas.md` — **não quebra** cálculo nem relatório (CA-5).
 *
 * **Determinismo.** Mesma matriz vigente → mesmo texto. Sem `now()`. A escrita em
 * `matriz-lacunas.md` é I/O lateral idempotente (IDR-010 §sub-decisão 3). O
 * `payload_hash` vem só do quiz, então não muda quando um texto é editado.
 *
 * **Snapshot.** O texto retornado é gravado em `diagnosticos.indicadores_calculados[*].mensagem`
 * no momento do cálculo (Motor::calcular). Diagnósticos antigos não recalculam
 * quando esta matriz evoluir — IDR-010 §sub-decisão 2.
 */
final class MatrizRecomendacoes
{
    public const FALLBACK = 'Recomendação em revisão.';

    /**
     * Devolve o texto da matriz para o par `(codigo, farol)` no setor pedido.
     *
     * @param  string  $codigo  Chave do indicador (snake_case Anexo D — ex.: `margem_bruta`).
     * @param  string  $farol  Cor do farol — `verde`, `amarelo`, `vermelho` — ou, no NCG absoluto, `positiva`, `negativa`.
     * @param  string  $setor  `industria` na V1.
     */
    public function texto(string $codigo, string $farol, string $setor = 'industria'): string
    {
        $fonte = app(FonteRecomendacoes::class);

        $texto = $fonte->texto($setor, $codigo, $farol);
        if ($texto !== null && $texto !== '') {
            return $texto;
        }

        $this->reportarLacuna($codigo, $farol, $setor, $fonte->versao($setor));

        return self::FALLBACK;
    }

    private function reportarLacuna(string $codigo, string $farol, string $setor, string $matrixVersion): void
    {
        Log::warning('matriz.recomendacao.lacuna', [
            'codigo' => $codigo,
            'farol' => $farol,
            'setor' => $setor,
            'matrix_version' => $matrixVersion,
        ]);

        $this->appendLacunaIdempotente($codigo, $farol, $setor, $matrixVersion);
    }

    /**
     * Append idempotente no arquivo `design/matriz-lacunas.md`.
     *
     * Idempotência: a mesma linha não é gravada duas vezes — se a lacuna já está
     * documentada, a escrita é silenciosamente ignorada. Isso garante que 100×
     * cálculos com o mesmo par lacunoso não inflam o arquivo.
     *
     * Em ambientes de teste sem o doc root, ou se a base_path() não existir, a
     * função silencia o I/O para não quebrar o teste por motivo de file system.
     */
    private function appendLacunaIdempotente(string $codigo, string $farol, string $setor, string $matrixVersion): void
    {
        $caminho = (string) config('motor.matriz_lacunas_path', '');
        if ($caminho === '') {
            return;
        }

        $dir = dirname($caminho);
        if (! is_dir($dir)) {
            return;
        }

        $linha = sprintf('- `%s` × `%s` (setor=%s, matrix_version=%s)', $codigo, $farol, $setor, $matrixVersion);

        $existente = is_file($caminho) ? (string) @file_get_contents($caminho) : '';
        if (str_contains($existente, $linha)) {
            return;
        }

        if ($existente === '') {
            $cabecalho = "# Lacunas da Matriz de Recomendações\n\n".
                "Pares `(indicador, farol)` que disparam fallback `\"Recomendação em revisão.\"` em runtime.\n".
                "Auto-append por `App\\Domain\\Motor\\MatrizRecomendacoes` (idempotente).\n\n";
            $existente = $cabecalho;
        }

        @file_put_contents($caminho, $existente.$linha."\n");
    }
}
