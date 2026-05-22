<?php

declare(strict_types=1);

namespace App\Observabilidade;

use App\Models\EventoProduto;
use App\Observabilidade\Excecoes\PiiEmEventoException;
use App\Support\RequestId;
use Illuminate\Support\Str;

/**
 * Emissor centralizado de eventos de produto (ADR-004 §Decisão 2).
 *
 * Síncrono inline na transação do agregado — atomicidade preservada (ADR-004 §2.3).
 * `request_id` injetado automaticamente do contexto atual.
 *
 * Valida PII na origem: lista de chaves proibidas em `propriedades`. Quando detectada,
 * lança `PiiEmEventoException` (ADR-004 §2.4 — defesa em camadas).
 */
final class EventLogger
{
    /** @var list<string> chaves exatas proibidas em `propriedades` de evento */
    private const FORBIDDEN_KEYS = [
        'password', 'senha', 'token', 'api_key', 'authorization', 'secret',
        'cpf', 'cnpj', 'email', 'telefone', 'phone',
        'nome_completo', 'endereco', 'cep', 'data_nascimento',
    ];

    /** @var list<string> regex proibidos em `propriedades` */
    private const FORBIDDEN_REGEX = [
        '/^faturamento(_.*)?$/',
        '/^balanco(_.*)?$/',
        '/^receita(_.*)?$/',
        '/^custo(_.*)?$/',
    ];

    /**
     * @param  array<string, mixed>  $propriedades
     */
    public static function emit(
        string $nomeEvento,
        array $propriedades = [],
        ?string $usuarioId = null,
        ?string $empresaId = null,
    ): EventoProduto {
        self::assertSemPii($propriedades);

        return EventoProduto::create([
            'evento_id' => (string) Str::uuid7(),
            'nome_evento' => $nomeEvento,
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'propriedades' => $propriedades,
            'request_id' => RequestId::get(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $propriedades
     */
    private static function assertSemPii(array $propriedades): void
    {
        foreach ($propriedades as $chave => $valor) {
            $normalizada = strtolower((string) $chave);

            if (in_array($normalizada, self::FORBIDDEN_KEYS, true)) {
                throw PiiEmEventoException::paraChave($normalizada);
            }

            foreach (self::FORBIDDEN_REGEX as $regex) {
                if (preg_match($regex, $normalizada) === 1) {
                    throw PiiEmEventoException::paraChave($normalizada);
                }
            }

            if (is_array($valor)) {
                self::assertSemPii($valor);
            }
        }
    }
}
