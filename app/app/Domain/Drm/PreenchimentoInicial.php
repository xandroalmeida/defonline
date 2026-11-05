<?php

declare(strict_types=1);

namespace App\Domain\Drm;

/**
 * Semente do formulário a partir do último lançamento anterior (Anexo K §K.9, DDR-003).
 *
 * É só leitura: devolve os valores já na máscara BRL, quais campos vieram
 * "repetidos" (a marca some ao editar/conferir) e os dois casos de herança
 * parcial. **Nunca distribui o total de um grupo entre itens** — ADR-007: não se
 * infere item a partir do total.
 */
final readonly class PreenchimentoInicial
{
    /**
     * @param  array<string, string>  $valores  campo => texto BRL
     * @param  list<string>  $repetidos
     * @param  array<string, int>  $totalAnterior  grupo => centavos; grupos que eram agregados e agora são detalhados
     * @param  list<string>  $somados  grupos cujo total foi preenchido pela soma dos itens do mês anterior
     */
    public function __construct(
        public array $valores = [],
        public array $repetidos = [],
        public array $totalAnterior = [],
        public array $somados = [],
    ) {}

    /**
     * @param  array<string, mixed>  $anterior  `lancamento_payload` do lançamento de origem
     * @param  array<string, Modo>  $modos  modos que o formulário vai usar agora
     */
    public static function de(Setor $setor, array $modos, array $anterior): self
    {
        $valores = [];
        $repetidos = [];
        $totalAnterior = [];
        $somados = [];
        $modosAnteriores = (array) ($anterior['modos'] ?? []);

        foreach (Lancamento::AVULSOS as $campo) {
            self::herdar($campo, $anterior, $valores, $repetidos);
        }

        foreach (Grupo::cases() as $grupo) {
            $agora = $modos[$grupo->value] ?? Modo::Detalhado;
            $antes = Modo::tryFrom((string) ($modosAnteriores[$grupo->value] ?? '')) ?? Modo::Detalhado;
            $totalDoMesAnterior = self::totalAnterior($grupo, $setor, $antes, $anterior);

            if ($agora === $antes) {
                foreach (Lancamento::camposDoGrupo($grupo, $setor, $modos) as $campo) {
                    self::herdar($campo, $anterior, $valores, $repetidos);
                }

                continue;
            }

            if ($agora === Modo::Agregado) {
                // Era detalhado: o total nasce da soma dos itens do mês anterior.
                $valores[$grupo->totalizador()] = Dinheiro::mascara($totalDoMesAnterior);
                $repetidos[] = $grupo->totalizador();
                $somados[] = $grupo->value;

                continue;
            }

            // Era agregado: itens sem de onde herdar. Só dizemos o total que havia.
            $totalAnterior[$grupo->value] = $totalDoMesAnterior;
        }

        return new self($valores, $repetidos, $totalAnterior, $somados);
    }

    /**
     * @param  array<string, mixed>  $anterior
     * @param  array<string, string>  $valores
     * @param  list<string>  $repetidos
     */
    private static function herdar(string $campo, array $anterior, array &$valores, array &$repetidos): void
    {
        $decimal = $anterior[$campo] ?? null;
        if (! is_string($decimal)) {
            return; // não coletado no mês de origem (ex.: campo suprimido): nada a herdar.
        }

        $valores[$campo] = Dinheiro::mascara(Dinheiro::deDecimal($decimal));
        $repetidos[] = $campo;
    }

    /**
     * @param  array<string, mixed>  $anterior
     */
    private static function totalAnterior(Grupo $grupo, Setor $setor, Modo $modo, array $anterior): int
    {
        $campos = $modo === Modo::Agregado ? [$grupo->totalizador()] : $grupo->itens($setor);

        return array_sum(array_map(
            static fn (string $campo): int => is_string($anterior[$campo] ?? null) ? Dinheiro::deDecimal($anterior[$campo]) : 0,
            $campos,
        ));
    }
}
