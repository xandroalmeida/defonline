<?php

declare(strict_types=1);

namespace App\Domain\Rfb;

use App\Domain\SituacaoCadastral;
use Illuminate\Support\Carbon;

/**
 * DTO imutável devolvido por `RfbCnpjClient::consultarCnpj()` em caso de sucesso
 * (STORY-015 CA-1).
 *
 * Espelha exatamente os 7 campos do CA-2 que o form de cadastro pré-preenche +
 * dois metadados ({@see $fonteProvedor}, {@see $consultadoAt}) que viram trilha
 * em `audit_logs`/`enriquecido_at` no submit final.
 *
 * `nome_fantasia`, `cnae` e `data_fundacao` são nullable porque CNPJs reais
 * podem vir sem esses campos (EI sem fantasia, faltando CNAE etc.) — o form
 * lida com isso mantendo o campo vazio para edição manual.
 */
final readonly class RfbCnpjResult
{
    public function __construct(
        public string $razaoSocial,
        public ?string $nomeFantasia,
        public ?string $cnae,
        public string $municipio,
        public string $uf,
        public SituacaoCadastral $situacaoCadastral,
        public ?Carbon $dataFundacao,
        public string $fonteProvedor,
        public Carbon $consultadoAt,
    ) {}

    /**
     * Projeção para preenchimento do form Livewire (STORY-015 CA-2). UF e CNAE
     * em formato canônico do banco; data em ISO `YYYY-MM-DD` aceito por
     * `<input type="date">`.
     *
     * @return array{razao_social: string, nome_fantasia: string, cnae: string, municipio: string, uf: string, situacao_cadastral: string, data_fundacao: string}
     */
    public function paraFormulario(): array
    {
        return [
            'razao_social' => $this->razaoSocial,
            'nome_fantasia' => $this->nomeFantasia ?? '',
            'cnae' => $this->cnae ?? '',
            'municipio' => $this->municipio,
            'uf' => $this->uf,
            'situacao_cadastral' => $this->situacaoCadastral->value,
            'data_fundacao' => $this->dataFundacao?->format('Y-m-d') ?? '',
        ];
    }
}
