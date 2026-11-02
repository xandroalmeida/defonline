<?php

declare(strict_types=1);

namespace App\Domain\Rfb;

/**
 * Endereço do estabelecimento como a RFB publica (STORY-063 CA-3). Todo campo
 * é opcional: o que o provedor não trouxer aparece como "Não informado pela
 * Receita" na tela.
 */
final readonly class Endereco
{
    public function __construct(
        public ?string $logradouro = null,
        public ?string $numero = null,
        public ?string $complemento = null,
        public ?string $bairro = null,
        public ?string $cep = null,
        public ?string $municipio = null,
        public ?string $uf = null,
    ) {}

    /**
     * @param  array<string, mixed>  $campos  chaves como as do construtor, valores brutos.
     */
    public static function deBruto(array $campos): self
    {
        $texto = static function (mixed $v): ?string {
            if (! is_string($v) && ! is_int($v)) {
                return null;
            }
            $t = trim((string) $v);

            return $t === '' ? null : $t;
        };

        $cep = $texto($campos['cep'] ?? null);
        $cep = $cep === null ? null : (preg_replace('/\D+/', '', $cep) ?: null);
        $uf = $texto($campos['uf'] ?? null);

        return new self(
            logradouro: $texto($campos['logradouro'] ?? null),
            numero: $texto($campos['numero'] ?? null),
            complemento: $texto($campos['complemento'] ?? null),
            bairro: $texto($campos['bairro'] ?? null),
            cep: $cep,
            municipio: $texto($campos['municipio'] ?? null),
            uf: $uf === null ? null : strtoupper($uf),
        );
    }

    /** `70040912` → `70040-912`; CEP fora do padrão sai como veio. */
    public function cepFormatado(): ?string
    {
        if ($this->cep === null || strlen($this->cep) !== 8) {
            return $this->cep;
        }

        return substr($this->cep, 0, 5).'-'.substr($this->cep, 5);
    }

    /** @return array<string, ?string> */
    public function toArray(): array
    {
        return [
            'logradouro' => $this->logradouro,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'bairro' => $this->bairro,
            'cep' => $this->cep,
            'municipio' => $this->municipio,
            'uf' => $this->uf,
        ];
    }

    /** @param array<string, mixed> $dados */
    public static function fromArray(array $dados): self
    {
        return self::deBruto($dados);
    }
}
