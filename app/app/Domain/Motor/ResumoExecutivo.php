<?php

declare(strict_types=1);

namespace App\Domain\Motor;

use App\Support\Relatorio\IndicadorFormatter;

/**
 * Geração determinística do Resumo Executivo (spec V2 §4.7.1).
 *
 * **Entrada.** O array `indicadores_calculados` produzido pelo {@see Motor},
 * com 15 entradas (14 do Anexo D + Ciclo Operacional informativo). Cada entrada
 * tem `{valor, farol, motivo, mensagem}` (ver {@see IndicadorResultado}).
 *
 * **Saída.** Estrutura JSON-friendly com veredito + destaques + linha de
 * fechamento; ver contrato no briefing-031. Caso fallback devolve estrutura
 * vazia + `mensagem_fallback`.
 *
 * **Pureza.** Sem `now()`, sem leitura de banco, sem Auth. Lê apenas:
 *  - `indicadores_calculados` recebido como argumento
 *  - `config('motor.faroes-industria.*')` para fronteiras de severidade
 *
 * **Determinismo.** Tudo é ordenado com critério primário + secundário
 * (Anexo D ASC) — não há `usort` instável escondido.
 *
 * **Escopo de tipos.** V1 só atende Indústria (motor 1.2.0). Quando
 * Comércio/Serviços entrarem, o classificador de severidade precisará escolher
 * a config certa pelo setor — refator pequeno, fora do escopo desta estória.
 */
final class ResumoExecutivo
{
    /**
     * Códigos dos 14 indicadores do Anexo D, em ordem (#1..#14).
     *
     * Esta lista é a fonte de verdade para:
     *  - **denominador 14** do fallback (`I / 14 ≥ 0,70`);
     *  - **whitelist** de indicadores que entram em V/A/Vd (filtragem por farol);
     *  - **desempate** por ordem do Anexo D (índice 0..13 = #1..#14).
     *
     * **NCG absoluto (#9)** entra na lista (faz parte do Anexo D) embora tenha
     * `farol = 'nenhum'` sempre — fica fora de V/A/Vd, mas conta no denominador 14.
     * **Ciclo Operacional** NÃO entra (não está no Anexo D — adicionado pela
     * STORY-030 como complementar).
     *
     * @var list<string>
     */
    public const CODIGOS_ANEXO_D = [
        'margem_bruta',           // #1
        'margem_ebitda',          // #2
        'margem_liquida',         // #3
        'divida_liquida_ebitda',  // #4
        'despesas_fin_ebitda',    // #5
        'fontes_recursos',        // #6
        'giro_ativo',             // #7
        'ciclo_financeiro',       // #8
        'ncg_absoluto',           // #9  (informativo — farol=nenhum)
        'ncg_vendas',             // #10
        'pmc',                    // #11
        'pme',                    // #12
        'pmr',                    // #13
        'inadimplencia',          // #14
    ];

    private const VEREDITOS_TEXTO = [
        'saudavel' => 'Sua empresa apresenta indicadores saudáveis no período avaliado.',
        'precisa_atencao' => 'Sua empresa apresenta pontos de atenção que merecem acompanhamento.',
        'em_alerta' => 'Sua empresa apresenta indicadores em estado de alerta que demandam ação.',
    ];

    private const LINHA_FIXA = 'Veja a tabela abaixo para análise detalhada e recomendações específicas.';

    private const MSG_FALLBACK = 'Não foi possível calcular indicadores suficientes para um resumo executivo. Revise os dados informados ou consulte a tabela abaixo.';

    private const MSG_TUDO_VERDE = 'Todos os indicadores avaliados estão em patamar saudável. Continue acompanhando.';

    private const LIMITE_MENSAGEM_DESTAQUE = 80;

    /**
     * @param  array<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}>  $indicadoresCalculados
     * @return array{
     *     motor_version_origem: string,
     *     veredito: string,
     *     veredito_texto: ?string,
     *     destaques_negativos: list<array{codigo: string, texto: string}>,
     *     destaque_positivo: ?array{codigo: string, texto: string},
     *     linha_fixa: ?string,
     *     fallback_acionado: bool,
     *     mensagem_fallback: ?string,
     *     mensagem_extra?: string
     * }
     */
    public function gerar(array $indicadoresCalculados, string $motorVersion): array
    {
        $anexoD = $this->filtrarAnexoD($indicadoresCalculados);

        [$V, $A, $Vd, $I] = $this->contar($anexoD);
        $N = $V + $A + $Vd;

        // Passo 5 — fallback ≥ 70%.
        if ($I / 14 >= 0.70) {
            return $this->resultadoFallback($motorVersion);
        }

        // N=0 não-fallback (todos farol=nenhum sem ser indisponível, ou só NCG abs presente):
        // patológico — defensivamente cai no fallback também.
        if ($N === 0) {
            return $this->resultadoFallback($motorVersion);
        }

        $veredito = $this->classificarVeredito($V, $A, $N);

        $destaquesNegativos = $this->selecionarDestaquesNegativos($anexoD);
        $destaquePositivo = $this->selecionarDestaquePositivo($anexoD);

        // Passo 6 — casos extremos opostos.
        $mensagemExtra = null;
        if ($veredito === 'saudavel' && $V === 0 && $A === 0 && $Vd === $N && $I === 0) {
            // Todos os indicadores com farol em verde + nenhum indisponível.
            // Spec: "linha única" no lugar de destaques negativos/positivos.
            $destaquesNegativos = [];
            $destaquePositivo = null;
            $mensagemExtra = self::MSG_TUDO_VERDE;
        }
        if ($veredito === 'em_alerta' && $V === $N) {
            // Todos vermelhos — sem destaque positivo (não há verde nem amarelo).
            $destaquePositivo = null;
        }

        $resultado = [
            'motor_version_origem' => $motorVersion,
            'veredito' => $veredito,
            'veredito_texto' => self::VEREDITOS_TEXTO[$veredito],
            'destaques_negativos' => $destaquesNegativos,
            'destaque_positivo' => $destaquePositivo,
            'linha_fixa' => self::LINHA_FIXA,
            'fallback_acionado' => false,
            'mensagem_fallback' => null,
        ];

        if ($mensagemExtra !== null) {
            $resultado['mensagem_extra'] = $mensagemExtra;
        }

        return $resultado;
    }

    /**
     * @param  array<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}>  $indicadores
     * @return array<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}>
     */
    private function filtrarAnexoD(array $indicadores): array
    {
        $anexoD = [];
        foreach (self::CODIGOS_ANEXO_D as $codigo) {
            if (isset($indicadores[$codigo])) {
                $anexoD[$codigo] = $indicadores[$codigo];
            }
        }

        return $anexoD;
    }

    /**
     * Conta V/A/Vd/I entre os 14 do Anexo D.
     *
     * Regras:
     *  - V/A/Vd contam APENAS indicadores com `farol IN (verde, amarelo, vermelho)`.
     *  - I conta indicadores com `valor === null` (indisponíveis).
     *  - NCG absoluto (farol=nenhum com valor) NÃO entra em V/A/Vd nem em I.
     *
     * @param  array<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}>  $anexoD
     * @return array{int, int, int, int} [V, A, Vd, I]
     */
    private function contar(array $anexoD): array
    {
        $V = 0;
        $A = 0;
        $Vd = 0;
        $I = 0;

        foreach ($anexoD as $ind) {
            if ($ind['valor'] === null) {
                $I++;

                continue;
            }
            match ($ind['farol']) {
                Farol::VERDE => $Vd++,
                Farol::AMARELO => $A++,
                Farol::VERMELHO => $V++,
                default => null, // farol=nenhum com valor (NCG abs) — não conta.
            };
        }

        return [$V, $A, $Vd, $I];
    }

    private function classificarVeredito(int $V, int $A, int $N): string
    {
        // Ordem das condições da spec (tabela §4.7.1, top-down):
        //  1) V/N ≥ 0.30 → em alerta
        //  2) V ≥ 1 OU A/N ≥ 0.50 → precisa de atenção
        //  3) V = 0 e A/N < 0.50 → saudável
        if ($V / $N >= 0.30) {
            return 'em_alerta';
        }
        if ($V >= 1 || $A / $N >= 0.50) {
            return 'precisa_atencao';
        }

        return 'saudavel';
    }

    /**
     * Passo 2 — até 2 destaques negativos (vermelhos primeiro, depois amarelos).
     *
     * @param  array<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}>  $anexoD
     * @return list<array{codigo: string, texto: string}>
     */
    private function selecionarDestaquesNegativos(array $anexoD): array
    {
        $vermelhos = $this->ordenarPorSeveridade($anexoD, Farol::VERMELHO);
        $amarelos = $this->ordenarPorSeveridade($anexoD, Farol::AMARELO);

        // Vermelhos primeiro (até 2), completando com amarelos.
        $selecionados = array_slice([...$vermelhos, ...$amarelos], 0, 2);

        return array_map(
            fn (string $codigo) => [
                'codigo' => $codigo,
                'texto' => $this->textoDestaque($codigo, $anexoD[$codigo]['mensagem'], prefixarPorOutroLado: false),
            ],
            $selecionados,
        );
    }

    /**
     * Passo 2 — até 1 destaque positivo (verde com maior favorabilidade).
     *
     * @param  array<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}>  $anexoD
     * @return ?array{codigo: string, texto: string}
     */
    private function selecionarDestaquePositivo(array $anexoD): ?array
    {
        $verdes = $this->ordenarPorFavorabilidade($anexoD);
        if ($verdes === []) {
            return null;
        }
        $codigo = $verdes[0];

        return [
            'codigo' => $codigo,
            'texto' => $this->textoDestaque($codigo, $anexoD[$codigo]['mensagem'], prefixarPorOutroLado: true),
        ];
    }

    /**
     * Ordena indicadores de uma cor por severidade DESC, com desempate por
     * ordem do Anexo D ASC.
     *
     * **Severidade** (interpretação para destaques negativos):
     *  - Vermelho: profundidade dentro da faixa vermelha (distância da fronteira amarela,
     *    normalizada pela amplitude da faixa vermelha). Quanto MAIS profundo, MAIS severo.
     *  - Amarelo: proximidade da fronteira do vermelho (distância da fronteira "boa",
     *    normalizada pela amplitude da faixa amarela). Quanto MAIS perto do vermelho,
     *    MAIS severo. Garante que entre dois amarelos, o que está prestes a ficar
     *    vermelho aparece primeiro.
     *
     * A spec §4.7.1 dá a fórmula literal `|valor − fronteira_amarela| / amplitude_faixa_vermelha`,
     * que vale exatamente para vermelhos. Para amarelos, mantemos a semântica de
     * "mesma regra" (severidade decrescente = pior primeiro) com a adaptação acima.
     *
     * @param  array<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}>  $anexoD
     * @return list<string> códigos ordenados (mais severo primeiro)
     */
    private function ordenarPorSeveridade(array $anexoD, string $farolAlvo): array
    {
        $candidatos = [];
        foreach ($anexoD as $codigo => $ind) {
            if ($ind['farol'] !== $farolAlvo || $ind['valor'] === null) {
                continue;
            }
            $candidatos[] = [
                'codigo' => $codigo,
                'severidade' => $this->severidade($codigo, (float) $ind['valor'], $farolAlvo),
                'anexoD' => $this->ordemAnexoD($codigo),
            ];
        }

        usort($candidatos, function (array $a, array $b): int {
            // Severidade DESC.
            if ($a['severidade'] !== $b['severidade']) {
                return $a['severidade'] < $b['severidade'] ? 1 : -1;
            }

            // Empate: Anexo D ASC.
            return $a['anexoD'] <=> $b['anexoD'];
        });

        return array_map(fn (array $c) => $c['codigo'], $candidatos);
    }

    /**
     * Ordena verdes por favorabilidade DESC (mais distante da fronteira do amarelo
     * no sentido bom), normalizada pela amplitude da faixa verde quando finita; caso
     * contrário, pela própria fronteira (mesmo critério da severidade vermelha,
     * só que com sinal trocado).
     *
     * @param  array<string, array{valor: float|int|null, farol: string, motivo: ?string, mensagem: string}>  $anexoD
     * @return list<string>
     */
    private function ordenarPorFavorabilidade(array $anexoD): array
    {
        $candidatos = [];
        foreach ($anexoD as $codigo => $ind) {
            if ($ind['farol'] !== Farol::VERDE || $ind['valor'] === null) {
                continue;
            }
            $candidatos[] = [
                'codigo' => $codigo,
                'favorabilidade' => $this->favorabilidade($codigo, (float) $ind['valor']),
                'anexoD' => $this->ordemAnexoD($codigo),
            ];
        }

        usort($candidatos, function (array $a, array $b): int {
            if ($a['favorabilidade'] !== $b['favorabilidade']) {
                return $a['favorabilidade'] < $b['favorabilidade'] ? 1 : -1;
            }

            return $a['anexoD'] <=> $b['anexoD'];
        });

        return array_map(fn (array $c) => $c['codigo'], $candidatos);
    }

    /**
     * Calcula a severidade conforme §4.7.1.
     *
     * Convenção de "amplitude" (a spec dá a fórmula `|valor − fronteira_amarela| /
     * amplitude_faixa_vermelha`, mas a amplitude é tecnicamente ilimitada — briefing
     * §Pegadinhas). Convenção do motor 1.2.0:
     *
     *   - maior_melhor: faixa vermelha é (-∞, Y]. Amplitude = Y (baseline em 0).
     *   - menor_melhor: faixa vermelha é (X, +∞). Amplitude = X (limite tolerável).
     *   - Amarelo (qualquer tipo): amplitude = largura da faixa amarela (max - min).
     *
     * Pré-condição: todos os 14 indicadores do Anexo D para Indústria têm Y > 0 e
     * X > 0. Se um indicador futuro violar isso, a divisão por valor não-positivo
     * vai falhar — escalar para o PO (briefing §Quando escalar).
     */
    private function severidade(string $codigo, float $valor, string $farol): float
    {
        $config = $this->faixa($codigo);
        $tipo = $config['tipo'];

        if ($farol === Farol::VERMELHO) {
            $fronteira = (float) $config['vermelho']['valor'];
            $distancia = $tipo === 'maior_melhor'
                ? ($fronteira - $valor)
                : ($valor - $fronteira);

            return max($distancia, 0.0) / $fronteira;
        }

        // Amarelo: proximidade da fronteira do vermelho, normalizada pela amplitude amarela.
        $minA = (float) $config['amarelo']['min'];
        $maxA = (float) $config['amarelo']['max'];
        $amplitudeAmarela = $maxA - $minA;

        // Para maior_melhor: amarelo (Y, X]; vermelho começa em Y; proximidade do vermelho = (X - valor).
        // Para menor_melhor: amarelo (Y, X]; vermelho começa em X; proximidade do vermelho = (valor - Y).
        $distancia = $tipo === 'maior_melhor'
            ? ($maxA - $valor)
            : ($valor - $minA);

        return max($distancia, 0.0) / $amplitudeAmarela;
    }

    private function favorabilidade(string $codigo, float $valor): float
    {
        $config = $this->faixa($codigo);
        $tipo = $config['tipo'];

        // maior_melhor: verde = valor > X (verde.valor). Distância no sentido bom = valor - X.
        // menor_melhor: verde = valor ≤ Y (verde.valor). Distância no sentido bom = Y - valor.
        $fronteira = (float) $config['verde']['valor'];
        $distancia = $tipo === 'maior_melhor'
            ? ($valor - $fronteira)
            : ($fronteira - $valor);

        return max($distancia, 0.0);
    }

    /**
     * Lê a faixa do indicador da config (sem validação — o whitelist
     * {@see self::CODIGOS_ANEXO_D} garante que só códigos cobertos chegam aqui,
     * e ncg_absoluto/ciclo_operacional nunca entram em severidade/favorabilidade
     * porque sempre têm farol=nenhum).
     *
     * @return array{tipo: string, verde: array{op: string, valor: float}, amarelo: array{min: float, max: float}, vermelho: array{op: string, valor: float}}
     */
    private function faixa(string $codigo): array
    {
        /** @var array{tipo: string, verde: array{op: string, valor: float}, amarelo: array{min: float, max: float}, vermelho: array{op: string, valor: float}} $config */
        $config = config('motor.faroes-industria.'.$codigo);

        return $config;
    }

    private function ordemAnexoD(string $codigo): int
    {
        $i = array_search($codigo, self::CODIGOS_ANEXO_D, strict: true);

        // CODIGOS_ANEXO_D é a whitelist — só códigos dela chegam até aqui.
        return $i === false ? PHP_INT_MAX : $i;
    }

    /**
     * Compõe o texto final de um destaque: prefixo + nome + ": " + mensagem truncada.
     */
    private function textoDestaque(string $codigo, string $mensagem, bool $prefixarPorOutroLado): string
    {
        $nome = IndicadorFormatter::nome($codigo);
        $msg = $this->truncarMensagem($mensagem, self::LIMITE_MENSAGEM_DESTAQUE);

        $prefixo = $prefixarPorOutroLado ? 'Por outro lado, ' : '';

        return $prefixo.$nome.': '.$msg;
    }

    /**
     * Trunca a mensagem em ~$limite chars preservando palavra; adiciona "…" se cortar.
     *
     * Estratégia:
     *  1. Se a mensagem já cabe → devolve sem modificação.
     *  2. Se há ponto final dentro do limite → primeira frase (mantém o ponto).
     *  3. Caso contrário, corta no último espaço/pontuação antes do limite e
     *     adiciona "…" (caractere único 'horizontal ellipsis').
     */
    private function truncarMensagem(string $mensagem, int $limite): string
    {
        if (mb_strlen($mensagem) <= $limite) {
            return $mensagem;
        }

        // Tenta primeira frase (`. ` ou final em `.`).
        $posPonto = mb_strpos($mensagem, '. ');
        if ($posPonto !== false && $posPonto + 1 <= $limite) {
            return mb_substr($mensagem, 0, $posPonto + 1);
        }

        $cortado = mb_substr($mensagem, 0, $limite);
        $ultimoEspaco = mb_strrpos($cortado, ' ');
        if ($ultimoEspaco !== false && $ultimoEspaco > (int) ($limite / 2)) {
            $cortado = mb_substr($cortado, 0, $ultimoEspaco);
        }
        $cortado = rtrim($cortado, " ,;:.\t\n\r");

        return $cortado.'…';
    }

    /**
     * @return array{
     *     motor_version_origem: string,
     *     veredito: string,
     *     veredito_texto: null,
     *     destaques_negativos: list<array{codigo: string, texto: string}>,
     *     destaque_positivo: null,
     *     linha_fixa: null,
     *     fallback_acionado: bool,
     *     mensagem_fallback: string
     * }
     */
    private function resultadoFallback(string $motorVersion): array
    {
        return [
            'motor_version_origem' => $motorVersion,
            'veredito' => 'fallback',
            'veredito_texto' => null,
            'destaques_negativos' => [],
            'destaque_positivo' => null,
            'linha_fixa' => null,
            'fallback_acionado' => true,
            'mensagem_fallback' => self::MSG_FALLBACK,
        ];
    }
}
