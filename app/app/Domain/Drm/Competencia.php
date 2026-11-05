<?php

declare(strict_types=1);

namespace App\Domain\Drm;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Mês/ano de um lançamento. Persistido como `date` com dia 01 (ADR-007);
 * `AAAA-MM` é o formato de trânsito na URL e na tela.
 */
final readonly class Competencia
{
    private function __construct(public CarbonImmutable $data) {}

    public static function deTexto(string $texto): self
    {
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $texto) !== 1) {
            throw new InvalidArgumentException("Competência inválida: {$texto}");
        }

        return new self(CarbonImmutable::createFromFormat('!Y-m', $texto));
    }

    public static function deData(\DateTimeInterface $data): self
    {
        return new self(CarbonImmutable::instance($data)->startOfMonth()->startOfDay());
    }

    /** Sugestão de D00: o mês anterior ao corrente. */
    public static function padrao(CarbonImmutable $hoje): self
    {
        return new self($hoje->startOfMonth()->subMonthNoOverflow()->startOfDay());
    }

    public function texto(): string
    {
        return $this->data->format('Y-m');
    }

    /** Valor da coluna `competencia`. */
    public function dia01(): string
    {
        return $this->data->format('Y-m-01');
    }

    /** Regra B2: o mês só pode ser lançado depois de terminar. */
    public function ehFutura(CarbonImmutable $hoje): bool
    {
        return $this->data->greaterThanOrEqualTo($hoje->startOfMonth()->startOfDay());
    }

    /** `agosto/2026`. */
    public function rotulo(): string
    {
        return self::MESES[$this->data->month - 1].'/'.$this->data->year;
    }

    public function mesPorExtenso(): string
    {
        return self::MESES[$this->data->month - 1];
    }

    /**
     * Os últimos `$quantidade` meses já terminados, do mais recente ao mais antigo.
     *
     * @return list<self>
     */
    public static function ultimosFechados(CarbonImmutable $hoje, int $quantidade = 24): array
    {
        $meses = [];
        $atual = self::padrao($hoje);
        for ($i = 0; $i < $quantidade; $i++) {
            $meses[] = $atual;
            $atual = new self($atual->data->subMonthNoOverflow());
        }

        return $meses;
    }

    private const MESES = [
        'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];
}
