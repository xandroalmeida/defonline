<?php

declare(strict_types=1);

namespace App\Domain\Rfb;

/**
 * Opção por um regime simplificado — Simples Nacional ou SIMEI (MEI) — como a
 * RFB publica (STORY-063 CA-3). `optante = null` significa que o provedor não
 * informou.
 */
final readonly class OpcaoRegime
{
    public function __construct(
        public ?bool $optante = null,
        public ?string $dataOpcao = null,
        public ?string $dataExclusao = null,
    ) {}

    public static function deBruto(mixed $optante, mixed $dataOpcao = null, mixed $dataExclusao = null): self
    {
        return new self(
            optante: is_bool($optante) ? $optante : null,
            dataOpcao: DataRfb::normalizar($dataOpcao),
            dataExclusao: DataRfb::normalizar($dataExclusao),
        );
    }

    public function rotulo(): ?string
    {
        return match ($this->optante) {
            true => 'Optante',
            false => 'Não optante',
            null => null,
        };
    }

    /** @return array{optante: ?bool, data_opcao: ?string, data_exclusao: ?string} */
    public function toArray(): array
    {
        return [
            'optante' => $this->optante,
            'data_opcao' => $this->dataOpcao,
            'data_exclusao' => $this->dataExclusao,
        ];
    }

    /** @param array<string, mixed> $dados */
    public static function fromArray(array $dados): self
    {
        return self::deBruto($dados['optante'] ?? null, $dados['data_opcao'] ?? null, $dados['data_exclusao'] ?? null);
    }
}
