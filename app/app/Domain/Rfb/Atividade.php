<?php

declare(strict_types=1);

namespace App\Domain\Rfb;

/**
 * Atividade econômica (CNAE) como a RFB publica — código de 7 dígitos +
 * descrição (STORY-063 CA-3).
 */
final readonly class Atividade
{
    public function __construct(
        public string $codigo,
        public ?string $descricao = null,
    ) {}

    /**
     * Normaliza o código vindo do provedor (`6422100`, `64.22-1-00`, int) para
     * 7 dígitos. Devolve null quando não há código utilizável.
     */
    public static function deBruto(mixed $codigo, mixed $descricao = null): ?self
    {
        $digitos = self::normalizarCodigo($codigo);
        if ($digitos === null) {
            return null;
        }

        $texto = is_string($descricao) ? trim($descricao) : '';

        return new self($digitos, $texto === '' ? null : $texto);
    }

    public static function normalizarCodigo(mixed $codigo): ?string
    {
        if (! is_string($codigo) && ! is_int($codigo)) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', (string) $codigo) ?? '';

        // `00.00-0-00` é o "sem atividade" do receitaws.
        if ($digitos === '' || (int) $digitos === 0) {
            return null;
        }

        return str_pad($digitos, 7, '0', STR_PAD_LEFT);
    }

    /** `6422100` → `6422-1/00` (formato oficial IBGE). */
    public function codigoFormatado(): string
    {
        return substr($this->codigo, 0, 4).'-'.substr($this->codigo, 4, 1).'/'.substr($this->codigo, 5, 2);
    }

    /** @return array{codigo: string, descricao: ?string} */
    public function toArray(): array
    {
        return ['codigo' => $this->codigo, 'descricao' => $this->descricao];
    }

    /** @param array<string, mixed> $dados */
    public static function fromArray(array $dados): ?self
    {
        return self::deBruto($dados['codigo'] ?? null, $dados['descricao'] ?? null);
    }
}
