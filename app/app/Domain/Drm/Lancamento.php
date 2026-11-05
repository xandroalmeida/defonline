<?php

declare(strict_types=1);

namespace App\Domain\Drm;

/**
 * Os valores de um lançamento, já interpretados, para um setor e um conjunto de modos.
 *
 * Só carrega o que o formulário *coleta* naquele setor e modo: campo suprimido pelo
 * setor, item de grupo agregado e totalizador de grupo detalhado ficam nulos
 * (Anexo K §K.4 e §K.5). Item opcional deixado em branco vira zero; os
 * obrigatórios em branco viram erro — o formulário não adivinha.
 */
final class Lancamento
{
    public const ERRO_OBRIGATORIO = 'obrigatorio';

    /** Campos monetários avulsos (fora dos quatro grupos). */
    public const AVULSOS = ['D01', 'D02', 'D40', 'D41', 'D42'];

    /** Obrigatórios do Anexo K §K.3 além dos totalizadores em modo agregado. */
    private const OBRIGATORIOS_FIXOS = ['D01', 'D02', 'D40'];

    /**
     * @param  array<string, int|null>  $centavos  D01..D42 (nulo = não coletado)
     * @param  array<string, string>  $erros  campo => obrigatorio|negativo|invalido
     * @param  array<string, Modo>  $modos  valor do Grupo => Modo
     */
    private function __construct(
        public readonly Setor $setor,
        public readonly array $modos,
        public readonly array $centavos,
        public readonly array $erros,
    ) {}

    /**
     * @param  array<string, Modo>  $modos  indexado pelo valor do Grupo
     * @param  array<string, string|null>  $texto  campo => texto digitado (máscara BRL)
     */
    public static function deTexto(Setor $setor, array $modos, array $texto): self
    {
        $centavos = [];
        $erros = [];

        foreach (self::camposColetados($setor, $modos) as $campo) {
            [$estado, $valor] = Dinheiro::interpretar($texto[$campo] ?? null);

            if ($estado === Dinheiro::VAZIO) {
                if (self::ehObrigatorio($campo)) {
                    $erros[$campo] = self::ERRO_OBRIGATORIO;
                }
                $centavos[$campo] = self::ehObrigatorio($campo) ? null : 0;

                continue;
            }

            if ($estado !== Dinheiro::OK) {
                $erros[$campo] = $estado;
                $centavos[$campo] = null;

                continue;
            }

            $centavos[$campo] = $valor;
        }

        return new self($setor, $modos, $centavos, $erros);
    }

    /**
     * Campos que o formulário coleta, na ordem do Anexo K.
     *
     * @param  array<string, Modo>  $modos
     * @return list<string>
     */
    public static function camposColetados(Setor $setor, array $modos): array
    {
        $campos = ['D01', 'D02'];
        foreach ([Grupo::Custo, Grupo::Vendas, Grupo::Administrativas] as $grupo) {
            $campos = [...$campos, ...self::camposDoGrupo($grupo, $setor, $modos)];
        }

        return [
            ...$campos,
            'D40',
            ...self::camposDoGrupo(Grupo::Financeiras, $setor, $modos),
            'D41',
            'D42',
        ];
    }

    /**
     * @param  array<string, Modo>  $modos
     * @return list<string>
     */
    public static function camposDoGrupo(Grupo $grupo, Setor $setor, array $modos): array
    {
        return ($modos[$grupo->value] ?? Modo::Detalhado) === Modo::Agregado
            ? [$grupo->totalizador()]
            : $grupo->itens($setor);
    }

    /**
     * Todos os campos monetários possíveis, D01..D42 (D00 é a competência).
     *
     * @return list<string>
     */
    public static function todosOsCampos(): array
    {
        return array_map(static fn (int $n): string => sprintf('D%02d', $n), range(1, 42));
    }

    public static function ehObrigatorio(string $campo): bool
    {
        if (in_array($campo, self::OBRIGATORIOS_FIXOS, true)) {
            return true;
        }

        $grupo = Grupo::doCampo($campo);

        return $grupo !== null && $campo === $grupo->totalizador();
    }

    public function valor(string $campo): int
    {
        return $this->centavos[$campo] ?? 0;
    }

    public function temErros(): bool
    {
        return $this->erros !== [];
    }

    /** Total do grupo: o digitado (agregado) ou a soma dos itens (detalhado). */
    public function total(Grupo $grupo): int
    {
        return array_sum(array_map(
            fn (string $campo): int => $this->valor($campo),
            self::camposDoGrupo($grupo, $this->setor, $this->modos),
        ));
    }

    public function receita(): int
    {
        return $this->valor('D01');
    }

    public function impostos(): int
    {
        return $this->valor('D02');
    }

    /** Soma de todas as despesas do mês: custo, vendas, administrativas, financeiras, retirada e outras despesas. */
    public function despesas(): int
    {
        return $this->total(Grupo::Custo) + $this->total(Grupo::Vendas) + $this->total(Grupo::Administrativas)
            + $this->total(Grupo::Financeiras) + $this->valor('D40') + $this->valor('D42');
    }

    /**
     * Lucro Líquido do Mês (`estrutura-drm` §1: L11). Só serve ao alerta A3 —
     * a demonstração de verdade é do motor (STORY-048), que deve concordar com isto.
     */
    public function lucroLiquido(): int
    {
        return $this->receita() - $this->impostos() - $this->despesas() + $this->valor('D41');
    }

    /**
     * Payload canônico: D01..D42 como decimal com duas casas (nulo se não coletado)
     * mais o modo de cada grupo — que é conteúdo e entra no hash (ADR-007).
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = [];
        foreach (self::todosOsCampos() as $campo) {
            $valor = $this->centavos[$campo] ?? null;
            $payload[$campo] = $valor === null ? null : Dinheiro::decimal($valor);
        }

        $modos = [];
        foreach (Grupo::cases() as $grupo) {
            $modos[$grupo->value] = ($this->modos[$grupo->value] ?? Modo::Detalhado)->value;
        }
        $payload['modos'] = $modos;

        return $payload;
    }
}
