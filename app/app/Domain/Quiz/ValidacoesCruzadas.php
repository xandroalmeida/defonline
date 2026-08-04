<?php

declare(strict_types=1);

namespace App\Domain\Quiz;

/**
 * Validações cruzadas DRE × Balanço (STORY-034, espec §6.6).
 *
 * Avalia as regras declaradas em `config/quiz/validacoes-cruzadas.php` contra os
 * valores do quiz e devolve a lista de {@see Alerta}s disparados.
 *
 * Cada regra dispara quando:
 *
 *     soma_ponderada(esquerda)  <operador>  fator × soma_ponderada(direita)
 *
 * Toda a lógica de negócio (quais campos, quais pesos, qual fórmula, qual texto)
 * vive na config — esta classe só interpreta o formato. Adicionar regra nova não
 * exige mexer aqui (CA-6).
 *
 * **Severidade (STORY-043).** Cada regra declara o que fazer quando dispara, e
 * quem consome escolhe o que quer ver:
 *
 *   - {@see SEVERIDADE_AVISO} — hipótese de erro estatístico. O componente mostra
 *     o banner inline no gate Bloco 3 → 4 e Roberto decide continuar mesmo assim.
 *   - {@see SEVERIDADE_BLOQUEIO} — inconsistência de input que o motor não deve
 *     receber. O componente segura o submit até o valor ser corrigido; não há
 *     "continuar mesmo assim" e nada é registrado em `alertas_aceitos`.
 *
 * O filtro é explícito de propósito: uma regra bloqueante nova não pode vazar
 * para o banner não-bloqueante só por ter sido acrescentada à config.
 */
final class ValidacoesCruzadas
{
    /** Aviso não-bloqueante — Roberto decide seguir (STORY-034). */
    public const SEVERIDADE_AVISO = 'warning';

    /** Bloqueio de consistência — segura o submit até correção (STORY-043). */
    public const SEVERIDADE_BLOQUEIO = 'blocking';

    /**
     * @param  array<string, float|int|string|null>  $valores  Mapa código do Anexo A → valor numérico.
     * @param  string|null  $severidade  Filtra as regras avaliadas; `null` avalia todas.
     * @return list<Alerta>
     */
    public function validar(array $valores, ?string $severidade = null): array
    {
        /** @var array<string, array<string, mixed>> $regras */
        $regras = (array) config('quiz.validacoes-cruzadas.regras', []);

        $alertas = [];
        foreach ($regras as $id => $regra) {
            if ($severidade !== null && ($regra['severidade'] ?? null) !== $severidade) {
                continue;
            }

            $alerta = $this->avaliar((string) $id, $regra, $valores);
            if ($alerta !== null) {
                $alertas[] = $alerta;
            }
        }

        return $alertas;
    }

    /**
     * @param  array<string, mixed>  $regra
     * @param  array<string, float|int|string|null>  $valores
     */
    private function avaliar(string $id, array $regra, array $valores): ?Alerta
    {
        /** @var array<string, mixed> $condicao */
        $condicao = $regra['condicao'];

        $esquerda = $this->somaPonderada((array) $condicao['esquerda'], $valores);
        $direita = $this->somaPonderada((array) $condicao['direita'], $valores);

        // Campo ausente/não-numérico — não dá para avaliar com segurança, pula a regra.
        if ($esquerda === null || $direita === null) {
            return null;
        }

        $fator = (float) ($condicao['fator'] ?? 1);

        if (! $this->dispara((string) $condicao['operador'], $esquerda, $fator * $direita)) {
            return null;
        }

        $percentual = $direita !== 0.0 ? round($esquerda / $direita * 100) : 0.0;

        return new Alerta(
            regra: $id,
            severidade: (string) $regra['severidade'],
            mensagem: $this->interpolar((string) $regra['mensagem'], $valores, $esquerda, $direita, $percentual),
            camposEnvolvidos: array_values((array) $regra['campos_envolvidos']),
            campoFoco: (string) $regra['campo_foco'],
            botaoLabel: (string) $regra['botao_label'],
            valorEnvolvido: $esquerda,
        );
    }

    /**
     * @param  array<string, int|float>  $pesos
     * @param  array<string, float|int|string|null>  $valores
     */
    private function somaPonderada(array $pesos, array $valores): ?float
    {
        $total = 0.0;
        foreach ($pesos as $campo => $peso) {
            $valor = $valores[$campo] ?? null;
            if (! is_numeric($valor)) {
                return null;
            }
            $total += (float) $valor * (float) $peso;
        }

        return $total;
    }

    private function dispara(string $operador, float $esquerda, float $limiar): bool
    {
        return match ($operador) {
            '>' => $esquerda > $limiar,
            '>=' => $esquerda >= $limiar,
            '<' => $esquerda < $limiar,
            '<=' => $esquerda <= $limiar,
            default => false,
        };
    }

    /**
     * @param  array<string, float|int|string|null>  $valores
     */
    private function interpolar(string $template, array $valores, float $esquerda, float $direita, float $percentual): string
    {
        $mapa = [
            ':esquerda' => $this->brl($esquerda),
            ':direita' => $this->brl($direita),
            ':percentual' => number_format($percentual, 0, ',', '.'),
        ];

        foreach ($valores as $campo => $valor) {
            if (is_numeric($valor)) {
                $mapa[':'.$campo] = $this->brl((float) $valor);
            }
        }

        return strtr($template, $mapa);
    }

    private function brl(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }
}
