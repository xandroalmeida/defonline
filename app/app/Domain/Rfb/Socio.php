<?php

declare(strict_types=1);

namespace App\Domain\Rfb;

/**
 * Integrante do quadro de sócios e administradores (QSA) como a RFB publica
 * (STORY-063 CA-3). `documento` vem mascarado pela própria RFB
 * (`***123456**`) — nunca é o CPF completo. Dado pessoal: não vai para log
 * nem evento (PDR-010).
 */
final readonly class Socio
{
    public function __construct(
        public string $nome,
        public ?string $qualificacao = null,
        public ?string $documento = null,
        public ?string $dataEntrada = null,
    ) {}

    public static function deBruto(mixed $nome, mixed $qualificacao = null, mixed $documento = null, mixed $dataEntrada = null): ?self
    {
        $nome = is_string($nome) ? trim($nome) : '';
        if ($nome === '') {
            return null;
        }

        $texto = static fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null;

        $qualificacao = $texto($qualificacao);
        // receitaws: "49-Sócio-Administrador" → "Sócio-Administrador".
        if ($qualificacao !== null) {
            $qualificacao = preg_replace('/^\d+\s*-\s*/', '', $qualificacao) ?? $qualificacao;
        }

        return new self(
            nome: $nome,
            qualificacao: $qualificacao,
            documento: $texto($documento),
            dataEntrada: DataRfb::normalizar($dataEntrada),
        );
    }

    /** @return array<string, ?string> */
    public function toArray(): array
    {
        return [
            'nome' => $this->nome,
            'qualificacao' => $this->qualificacao,
            'documento' => $this->documento,
            'data_entrada' => $this->dataEntrada,
        ];
    }

    /** @param array<string, mixed> $dados */
    public static function fromArray(array $dados): ?self
    {
        return self::deBruto($dados['nome'] ?? null, $dados['qualificacao'] ?? null, $dados['documento'] ?? null, $dados['data_entrada'] ?? null);
    }
}
