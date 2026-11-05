<?php

declare(strict_types=1);

namespace App\Domain\Drm;

/**
 * Os quatro grupos que alternam entre total único e detalhamento (Anexo K §K.5).
 *
 * Fonte única de "qual campo pertence a qual grupo": o formulário, a contagem de
 * campos do seletor de modo e a soma do total saem daqui.
 */
enum Grupo: string
{
    case Custo = 'custo';
    case Vendas = 'vendas';
    case Administrativas = 'administrativas';
    case Financeiras = 'financeiras';

    /** Campo totalizador: o que o usuário digita no modo agregado. */
    public function totalizador(): string
    {
        return match ($this) {
            self::Custo => 'D03',
            self::Vendas => 'D08',
            self::Administrativas => 'D15',
            self::Financeiras => 'D37',
        };
    }

    /**
     * Itens do modo detalhado, já sem o que o setor não coleta.
     *
     * @return list<string>
     */
    public function itens(Setor $setor): array
    {
        $todos = match ($this) {
            self::Custo => ['D04', 'D05', 'D06', 'D07'],
            self::Vendas => ['D09', 'D10', 'D11', 'D12', 'D13', 'D14'],
            self::Administrativas => array_map(
                static fn (int $n): string => 'D'.$n,
                range(16, 36),
            ),
            self::Financeiras => ['D38', 'D39'],
        };

        return array_values(array_diff($todos, $setor->camposSuprimidos()));
    }

    /** Quantos campos o usuário preenche no modo dado — o argumento do seletor (DDR-002). */
    public function custoEmCampos(Setor $setor, Modo $modo): int
    {
        return $modo === Modo::Agregado ? 1 : count($this->itens($setor));
    }

    /** Grupo a que o campo pertence, ou null para campos avulsos (D01, D02, D40, D41, D42). */
    public static function doCampo(string $campo): ?self
    {
        foreach (self::cases() as $grupo) {
            if ($campo === $grupo->totalizador() || in_array($campo, $grupo->itens(Setor::Industria), true)) {
                return $grupo;
            }
        }

        return null;
    }
}
