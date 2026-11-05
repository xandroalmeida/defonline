<?php

declare(strict_types=1);

namespace App\Support\Drm;

/** Uma competência da espinha da série, pronta para a tela: textos formatados, nenhuma decisão pendente. */
final readonly class LinhaDaSerie
{
    public const LANCADO = 'lancado';

    public const NAO_LANCADO = 'nao_lancado';

    public const RASCUNHO = 'rascunho';

    public function __construct(
        public string $competencia,
        public string $estado,
        public string $rotulo,
        public string $nomeAcessivel,
        public string $estadoTexto,
        public string $acaoTipo,
        public string $acaoRotulo,
        public string $acaoAria,
        public string $acaoUrl,
        public ?string $mesUrl = null,
        public bool $retificado = false,
        public ?string $corrigidoTexto = null,
        public bool $atipico = false,
        public ?string $explicacao = null,
        public bool $prejuizo = false,
        public ?string $valor = null,
        public ?string $valorSemMoeda = null,
        public ?string $percentual = null,
        public ?string $percentualAusente = null,
        public ?string $vendeu = null,
        public ?string $vsAnterior = null,
        public bool $vsAnteriorOk = false,
        public ?string $vsMedia = null,
        public bool $vsMediaOk = false,
        public ?string $rascunhoTexto = null,
    ) {}

    public function lancado(): bool
    {
        return $this->estado === self::LANCADO;
    }
}
