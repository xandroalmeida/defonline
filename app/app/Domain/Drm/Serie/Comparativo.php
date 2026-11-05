<?php

declare(strict_types=1);

namespace App\Domain\Drm\Serie;

/**
 * Um comparativo da série: disponível (com o valor) ou indisponível (com o motivo semântico).
 *
 * Nunca existe "zero disfarçado": quem não pode ser calculado carrega `motivo`, e a tela o diz
 * em palavras (DDR-005). `meses` é quantos meses lançados entraram na conta — o acumulado e as
 * médias somam só o que existe, e a tela precisa poder dizer isso.
 */
final readonly class Comparativo
{
    /** Não há lançamento anterior a este mês: é o primeiro da série. */
    public const PRIMEIRO_LANCAMENTO = 'indisponivel:primeiro_lancamento';

    /** O mês de base não foi lançado (ausência, nunca zero — ADR-007). */
    public const SEM_LANCAMENTO = 'indisponivel:sem_lancamento';

    /** Menos de 3 competências anteriores na janela de 12 meses. */
    public const HISTORICO_INSUFICIENTE = 'indisponivel:historico_insuficiente';

    /** A base da variação é zero: dividir por ela não diz nada. */
    public const BASE_ZERO = 'indisponivel:base_zero';

    /** Item de grupo lançado de forma agregada em algum mês da conta: o item não se infere do total. */
    public const GRUPO_AGREGADO = 'indisponivel:grupo_agregado';

    /** Todos os meses da janela sem faturamento: não há percentual sobre a receita. */
    public const SEM_FATURAMENTO = 'indisponivel:sem_faturamento';

    private function __construct(
        public ?string $percentual,
        public ?int $centavos,
        public int $meses,
        public ?string $motivo,
    ) {}

    /** Variação ou participação, em percentual do snapshot (4 casas). */
    public static function percentual(string $percentual, int $meses): self
    {
        return new self($percentual, null, $meses, null);
    }

    public static function centavos(int $centavos, int $meses): self
    {
        return new self(null, $centavos, $meses, null);
    }

    public static function indisponivel(string $motivo): self
    {
        return new self(null, null, 0, $motivo);
    }

    public function disponivel(): bool
    {
        return $this->motivo === null;
    }
}
