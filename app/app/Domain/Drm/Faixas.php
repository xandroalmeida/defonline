<?php

declare(strict_types=1);

namespace App\Domain\Drm;

/**
 * Faixas de farol do DRM — a regra que decide **se há farol**.
 *
 * # Por que isto é motor e não tela
 *
 * A regra central do CA-5 da `STORY-052` é uma só: **faixa `nao_calibrada` não
 * produz farol.** Ela poderia morar na view que desenha o pill — e aí a
 * próxima superfície (exportação em planilha, PDF, e-mail de resumo, API)
 * teria que reimplementá-la, e uma delas esqueceria. O `PDR-009` §2 item 3 é
 * explícito: a regra é do motor, para que nenhuma superfície futura consiga
 * contorná-la por engano.
 *
 * Publicar farol sobre faixa não validada é dizer a um microempresário que a
 * margem dele está vermelha com base num palpite — pior do que não dizer nada,
 * e exatamente o risco que o `NRF §9.3` existe para evitar.
 *
 * # O que esta classe NÃO faz
 *
 * Não desenha nada e não escolhe cor de token: devolve `verde`, `amarelo`,
 * `vermelho` ou `null`. A apresentação é da `STORY-054`, que herda o
 * tratamento visual do `DDR-006` sem inventar o seu.
 *
 * @phpstan-type Faixa array{estado: string, sentido: string, verde: array{min: float|null, max: float|null}, amarelo: array{min: float|null, max: float|null}, vermelho: array{min: float|null, max: float|null}}
 */
final class Faixas
{
    public const CALIBRADA = 'calibrada';

    public const NAO_CALIBRADA = 'nao_calibrada';

    /** @var list<string> */
    public const CORES = ['verde', 'amarelo', 'vermelho'];

    /**
     * Farol de uma linha da demonstração, ou `null` quando não há farol.
     *
     * Devolve `null` em quatro situações, e **nenhuma delas é erro**:
     *
     *   - a linha não tem faixa configurada (nem toda linha tem farol);
     *   - a faixa está `nao_calibrada` — o caso do dia um, e o comportamento
     *     correto: a linha aparece sem semáforo;
     *   - o percentual é `null` (mês sem faturamento, Anexo K §K.7);
     *   - o valor não caiu em nenhuma faixa declarada, o que denuncia faixa
     *     calibrada com buraco — e devolver `null` é mais honesto do que
     *     escolher a cor mais próxima.
     *
     * @param  string  $linha  Código da linha (`L5`, `L7`, `L11`…).
     * @param  float|null  $percentual  Percentual sobre a Receita Bruta, em pontos percentuais.
     */
    public function farol(string $linha, ?float $percentual, string $setor = 'industria'): ?string
    {
        if ($percentual === null) {
            return null;
        }

        $faixa = $this->faixa($linha, $setor);
        if ($faixa === null || ($faixa['estado'] ?? self::NAO_CALIBRADA) !== self::CALIBRADA) {
            return null;
        }

        foreach (self::CORES as $cor) {
            $limites = $faixa[$cor] ?? null;
            if (is_array($limites) && $this->dentro($percentual, $limites)) {
                return $cor;
            }
        }

        return null;
    }

    /**
     * Se a linha tem faixa validada pela EBC. Falso é o estado esperado hoje.
     */
    public function calibrada(string $linha, string $setor = 'industria'): bool
    {
        $faixa = $this->faixa($linha, $setor);

        return is_array($faixa) && ($faixa['estado'] ?? null) === self::CALIBRADA;
    }

    /**
     * Configuração bruta da faixa — usada pelo teste arquitetural e por quem
     * precisa exibir a origem da calibração.
     *
     * @return array<string, mixed>|null
     */
    public function faixa(string $linha, string $setor = 'industria'): ?array
    {
        /** @var array<string, mixed>|null $faixa */
        $faixa = config("drm.faixas-{$setor}.linhas.{$linha}");

        return is_array($faixa) ? $faixa : null;
    }

    /**
     * `min` inclusivo, `max` exclusivo; `null` em qualquer lado é "sem limite".
     *
     * @param  array{min: float|int|null, max: float|int|null}  $limites
     */
    private function dentro(float $percentual, array $limites): bool
    {
        $min = $limites['min'] ?? null;
        $max = $limites['max'] ?? null;

        // Faixa sem nenhum limite não classifica nada — é o formato de uma
        // faixa não preenchida, e cair aqui como "serve para tudo" faria a
        // primeira cor da lista vencer sempre.
        if ($min === null && $max === null) {
            return false;
        }

        if ($min !== null && $percentual < (float) $min) {
            return false;
        }

        return ! ($max !== null && $percentual >= (float) $max);
    }
}
