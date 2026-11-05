<?php

declare(strict_types=1);

namespace App\Domain\Drm;

/**
 * Uma regra do Anexo K §K.6 que disparou (bloqueio `B*` ou alerta `A*`).
 *
 * `contexto` já traz os valores formatados para o texto (`valor_receita` etc.);
 * quem exibe acrescenta o que depende de conteúdo (rótulo do campo, empresa).
 */
final readonly class Ocorrencia
{
    /**
     * @param  array<string, string>  $contexto
     */
    public function __construct(
        public string $codigo,
        public ?string $campo = null,
        public array $contexto = [],
        public ?int $valorEnvolvido = null,
    ) {}
}
