<?php

declare(strict_types=1);

namespace App\Domain\Quiz;

use App\Domain\Cpf;
use App\Domain\Motor\QuizPayloadCanonicalizer;

/**
 * Lista dinâmica de sócios do Bloco 4 do quiz Indústria (STORY-038).
 *
 * Cada sócio é um objeto `{cpf, nome}`. Substitui os três CPFs fixos
 * (Q21/Q22/Q23) por uma lista `socios[]` com **mínimo 1** e **cap de 10**
 * (sanidade — não regra de negócio externa).
 *
 * Classe pura, sem framework — vive em `app/Domain` por ser regra de negócio
 * do hot path do quiz (gate de cobertura ≥98% do `phpunit-domain.xml`). O
 * componente Livewire do quiz delega aqui a validação por linha e a forma
 * canônica que vai para o `payload_hash`.
 *
 * Decisões de produto (STORY-038 + ajuste pós-devolutiva):
 *   - mínimo 1, máximo 10; CPF **e** nome obrigatórios em cada sócio preenchido;
 *   - duplicidade é por **CPF** (a pessoa) e é **bloqueante** no submit;
 *   - ordem canônica é **alfabética pelo CPF** (independe da digitação) — o
 *     {@see QuizPayloadCanonicalizer} preserva ordem de listas por design, então
 *     a ordenação acontece aqui; ele cuida da ordenação das chaves de cada
 *     objeto (`cpf`, `nome`) e da normalização NFC do nome.
 */
final class ValidaSocios
{
    public const MINIMO = 1;

    public const MAXIMO = 10;

    /** Erro de lista vazia — mora em `socios.0.cpf`, mas é da lista inteira. */
    public const ERRO_MINIMO = 'Informe o CPF de ao menos um sócio.';

    /**
     * Forma canônica da lista: linhas 100% vazias descartadas (CA-7), CPF
     * normalizado para 11 dígitos, nome aparado, e a lista ordenada
     * alfabeticamente pelo CPF (CA-9). Reindexada como lista sequencial.
     *
     * @param  list<array{cpf?: string|null, nome?: string|null}>  $socios
     * @return list<array{cpf: string, nome: string}>
     */
    public static function canonicalizar(array $socios): array
    {
        $itens = [];
        foreach ($socios as $socio) {
            $cpf = Cpf::normalizar((string) ($socio['cpf'] ?? ''));
            $nome = trim((string) ($socio['nome'] ?? ''));
            if ($cpf === '' && $nome === '') {
                continue;
            }
            $itens[] = ['cpf' => $cpf, 'nome' => $nome];
        }

        usort($itens, fn (array $a, array $b): int => strcmp($a['cpf'], $b['cpf']));

        return $itens;
    }

    /**
     * Erros de validação mapeados pelas chaves Livewire `socios.{i}.cpf` e
     * `socios.{i}.nome`, onde `{i}` é o índice ORIGINAL da linha — assim o erro
     * aparece exatamente no campo que o causou. Lista vazia ⇒ válido.
     *
     * Regras:
     *   - linha 100% vazia (sem CPF e sem nome) é ignorada (CA-7);
     *   - CPF ausente / inválido / duplicado → erro em `socios.{i}.cpf`
     *     (inválido tem precedência sobre duplicidade; CA-4, CA-5);
     *   - nome ausente → erro em `socios.{i}.nome`;
     *   - nenhuma linha preenchida → mínimo de 1 sócio em `socios.0.cpf` (CA-1);
     *   - mais de {@see MAXIMO} preenchidos → cap defensivo na última linha
     *     (CA-6; a UI já desabilita o "+ Adicionar").
     *
     * @param  list<array{cpf?: string|null, nome?: string|null}>  $socios
     * @return array<string, string>
     */
    public static function erros(array $socios): array
    {
        $erros = [];
        $vistos = [];
        $preenchidas = 0;
        $ultimoIndicePreenchido = null;

        foreach ($socios as $i => $socio) {
            $cpf = trim((string) ($socio['cpf'] ?? ''));
            $nome = trim((string) ($socio['nome'] ?? ''));
            if ($cpf === '' && $nome === '') {
                continue;
            }

            $preenchidas++;
            $ultimoIndicePreenchido = $i;

            if ($cpf === '') {
                $erros["socios.$i.cpf"] = 'Informe o CPF do sócio.';
            } elseif (! Cpf::valido($cpf)) {
                $erros["socios.$i.cpf"] = 'CPF inválido — confira os dígitos.';
            } else {
                $digitos = Cpf::normalizar($cpf);
                if (isset($vistos[$digitos])) {
                    $erros["socios.$i.cpf"] = 'CPF duplicado na lista — cada sócio só pode aparecer uma vez.';
                } else {
                    $vistos[$digitos] = true;
                }
            }

            if ($nome === '') {
                $erros["socios.$i.nome"] = 'Informe o nome do sócio.';
            }
        }

        if ($preenchidas === 0) {
            $erros['socios.0.cpf'] = self::ERRO_MINIMO;
        }

        if ($preenchidas > self::MAXIMO) {
            $erros["socios.$ultimoIndicePreenchido.cpf"] = 'Limite de 10 sócios por empresa.';
        }

        return $erros;
    }
}
