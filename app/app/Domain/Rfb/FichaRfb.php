<?php

declare(strict_types=1);

namespace App\Domain\Rfb;

/**
 * Ficha cadastral completa da RFB além dos campos-núcleo do
 * {@see RfbCnpjResult} (STORY-063 CA-3; PDR-010).
 *
 * É persistida inteira em `empresas_analisadas.ficha_rfb` e nunca editada pelo
 * usuário — só substituída por uma nova consulta (STORY-064).
 */
final readonly class FichaRfb
{
    /**
     * @param  list<Atividade>  $cnaesSecundarios
     * @param  list<Socio>  $socios
     */
    public function __construct(
        public ?string $naturezaJuridica = null,
        public ?string $porte = null,
        public ?string $capitalSocial = null,
        public ?string $dataSituacao = null,
        public ?Atividade $cnaePrincipal = null,
        public array $cnaesSecundarios = [],
        public Endereco $endereco = new Endereco,
        public array $socios = [],
        public OpcaoRegime $simples = new OpcaoRegime,
        public OpcaoRegime $simei = new OpcaoRegime,
    ) {}

    /** Capital social como decimal com 2 casas (`1500.5` → `1500.50`); null se não numérico. */
    public static function normalizarCapital(mixed $bruto): ?string
    {
        if (! is_int($bruto) && ! is_float($bruto) && ! (is_string($bruto) && is_numeric(trim($bruto)))) {
            return null;
        }

        return number_format((float) $bruto, 2, '.', '');
    }

    /** `120000000000.00` → `R$ 120.000.000.000,00`. */
    public function capitalSocialFormatado(): ?string
    {
        if ($this->capitalSocial === null) {
            return null;
        }

        return 'R$ '.number_format((float) $this->capitalSocial, 2, ',', '.');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'natureza_juridica' => $this->naturezaJuridica,
            'porte' => $this->porte,
            'capital_social' => $this->capitalSocial,
            'data_situacao' => $this->dataSituacao,
            'cnae_principal' => $this->cnaePrincipal?->toArray(),
            'cnaes_secundarios' => array_map(static fn (Atividade $a) => $a->toArray(), $this->cnaesSecundarios),
            'endereco' => $this->endereco->toArray(),
            'socios' => array_map(static fn (Socio $s) => $s->toArray(), $this->socios),
            'simples' => $this->simples->toArray(),
            'simei' => $this->simei->toArray(),
        ];
    }

    /** @param array<string, mixed> $dados */
    public static function fromArray(array $dados): self
    {
        $lista = static fn (mixed $v): array => is_array($v) ? array_values(array_filter($v, 'is_array')) : [];
        $texto = static fn (mixed $v): ?string => is_string($v) && $v !== '' ? $v : null;

        return new self(
            naturezaJuridica: $texto($dados['natureza_juridica'] ?? null),
            porte: $texto($dados['porte'] ?? null),
            capitalSocial: self::normalizarCapital($dados['capital_social'] ?? null),
            dataSituacao: DataRfb::normalizar($dados['data_situacao'] ?? null),
            cnaePrincipal: is_array($dados['cnae_principal'] ?? null) ? Atividade::fromArray($dados['cnae_principal']) : null,
            cnaesSecundarios: array_values(array_filter(array_map(Atividade::fromArray(...), $lista($dados['cnaes_secundarios'] ?? null)))),
            endereco: Endereco::fromArray(is_array($dados['endereco'] ?? null) ? $dados['endereco'] : []),
            socios: array_values(array_filter(array_map(Socio::fromArray(...), $lista($dados['socios'] ?? null)))),
            simples: OpcaoRegime::fromArray(is_array($dados['simples'] ?? null) ? $dados['simples'] : []),
            simei: OpcaoRegime::fromArray(is_array($dados['simei'] ?? null) ? $dados['simei'] : []),
        );
    }
}
