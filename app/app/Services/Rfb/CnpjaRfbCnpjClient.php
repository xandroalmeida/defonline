<?php

declare(strict_types=1);

namespace App\Services\Rfb;

use App\Domain\Rfb\Atividade;
use App\Domain\Rfb\DataRfb;
use App\Domain\Rfb\Endereco;
use App\Domain\Rfb\FichaRfb;
use App\Domain\Rfb\OpcaoRegime;
use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\Rfb\RfbCnpjStatus;
use App\Domain\Rfb\Socio;
use App\Domain\SituacaoCadastral;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;

/**
 * Cliente real da RFB via cnpja.com (STORY-018 CA-2; IDR-004).
 *
 * Dois endpoints distintos:
 * - `https://open.cnpja.com/office/{cnpj}` — Open API gratuito (3 RPM), sem
 *   token; **default em local/homologação**.
 * - `https://api.cnpja.com/office/{cnpj}` — plano pago, exige header
 *   `Authorization: <token>` (sem "Bearer"). Ativado sobrescrevendo
 *   `RFB_CNPJA_BASE_URL` + `RFB_CNPJA_API_KEY` via env.
 *
 * Mapeamento de campos confirmado contra a resposta REAL do Open API em
 * 2026-05-23 (smoke contratual, CNPJ 00.000.000/0001-91 — Banco do Brasil):
 *
 *     {
 *       "taxId":   "00000000000191",
 *       "company": { "name": "BANCO DO BRASIL SA" },
 *       "alias":   "Direcao Geral",       // pode vir ausente para EI
 *       "founded": "1966-08-01",          // ISO YYYY-MM-DD
 *       "status":  { "id": 2, "text": "Ativa" },
 *       "address": {
 *           "municipality": 5300108,      // ⚠ código IBGE (numérico) — não usar
 *           "city":         "Brasília",   // ← nome do município (string) ←
 *           "state":        "DF"
 *       },
 *       "mainActivity": { "id": 6422100, "text": "..." }
 *     }
 *
 * **Gotcha histórica:** versões anteriores do schema serviam o nome do
 * município em `address.municipality` (string). O Open API atual serve o
 * código IBGE nesse campo e o nome em `address.city`. Cliente lê `city`;
 * cai para `municipality` (string) se vier — defesa para o plano pago caso
 * mantenha o schema antigo.
 */
final class CnpjaRfbCnpjClient extends AbstractHttpRfbCnpjClient
{
    private const PROVEDOR = 'cnpja';

    protected function provedor(): string
    {
        return self::PROVEDOR;
    }

    protected function endpoint(string $cnpj): string
    {
        return "/office/{$cnpj}";
    }

    protected function parseSucesso(Response $response): RfbCnpjResult
    {
        /** @var array<string, mixed> $body */
        $body = (array) $response->json();

        $razaoSocial = self::stringOuFalha($body, 'company.name', 'razão social');
        $municipio = self::nomeMunicipio($body);
        $uf = self::stringOuFalha($body, 'address.state', 'UF');

        return new RfbCnpjResult(
            razaoSocial: $razaoSocial,
            nomeFantasia: self::stringOpcional($body, 'alias'),
            cnae: Atividade::normalizarCodigo($body['mainActivity']['id'] ?? null),
            municipio: $municipio,
            uf: strtoupper($uf),
            situacaoCadastral: self::mapearSituacao(self::stringOpcional($body, 'status.text')),
            dataFundacao: self::parseDataIso(self::stringOpcional($body, 'founded')),
            fonteProvedor: self::PROVEDOR,
            consultadoAt: Carbon::now(),
            ficha: self::ficha($body, $municipio, strtoupper($uf)),
        );
    }

    /**
     * Ficha completa (STORY-063 CA-3/CA-10). Formato conferido contra a
     * resposta real do Open API em 2026-11-02 (fixture `sucesso-completo.json`).
     *
     * @param  array<string, mixed>  $body
     */
    private static function ficha(array $body, string $municipio, string $uf): FichaRfb
    {
        $lista = static fn (mixed $v): array => is_array($v) ? array_values(array_filter($v, 'is_array')) : [];

        return new FichaRfb(
            naturezaJuridica: self::stringOpcional($body, 'company.nature.text'),
            porte: self::stringOpcional($body, 'company.size.text'),
            capitalSocial: FichaRfb::normalizarCapital(data_get($body, 'company.equity')),
            dataSituacao: DataRfb::normalizar($body['statusDate'] ?? null),
            cnaePrincipal: Atividade::deBruto(data_get($body, 'mainActivity.id'), data_get($body, 'mainActivity.text')),
            cnaesSecundarios: array_values(array_filter(array_map(
                static fn (array $a): ?Atividade => Atividade::deBruto($a['id'] ?? null, $a['text'] ?? null),
                $lista($body['sideActivities'] ?? null),
            ))),
            endereco: Endereco::deBruto([
                'logradouro' => data_get($body, 'address.street'),
                'numero' => data_get($body, 'address.number'),
                'complemento' => data_get($body, 'address.details'),
                'bairro' => data_get($body, 'address.district'),
                'cep' => data_get($body, 'address.zip'),
                'municipio' => $municipio,
                'uf' => $uf,
            ]),
            socios: array_values(array_filter(array_map(
                static fn (array $m): ?Socio => Socio::deBruto(
                    data_get($m, 'person.name'),
                    data_get($m, 'role.text'),
                    data_get($m, 'person.taxId'),
                    $m['since'] ?? null,
                ),
                $lista(data_get($body, 'company.members')),
            ))),
            simples: self::regime(data_get($body, 'company.simples')),
            simei: self::regime(data_get($body, 'company.simei')),
        );
    }

    /**
     * `{optant, since, history: [{from, until}]}` — quando não optante, a data
     * de exclusão é o `until` do último período do histórico.
     */
    private static function regime(mixed $bruto): OpcaoRegime
    {
        if (! is_array($bruto)) {
            return new OpcaoRegime;
        }

        $optante = $bruto['optant'] ?? null;
        $historico = is_array($bruto['history'] ?? null) ? array_values(array_filter($bruto['history'], 'is_array')) : [];
        $ultimo = $historico === [] ? [] : $historico[count($historico) - 1];

        return OpcaoRegime::deBruto(
            $optante,
            $bruto['since'] ?? ($ultimo['from'] ?? null),
            $optante === false ? ($ultimo['until'] ?? null) : null,
        );
    }

    /**
     * Nome do município com fallback entre os dois campos que o cnpja já usou.
     *
     * Open API atual: `address.city` (string). Schema legado (e possivelmente
     * plano pago em versões mais antigas): `address.municipality` (string).
     * Hoje `address.municipality` no Open API é o **código IBGE numérico** —
     * por isso só aceitamos `municipality` se vier string.
     *
     * @param  array<string, mixed>  $body
     */
    private static function nomeMunicipio(array $body): string
    {
        $city = data_get($body, 'address.city');
        if (is_string($city) && $city !== '') {
            return $city;
        }

        $municipality = data_get($body, 'address.municipality');
        if (is_string($municipality) && $municipality !== '') {
            return $municipality;
        }

        throw new RfbCnpjFalhouException(
            RfbCnpjStatus::Erro5xx,
            self::PROVEDOR,
            'Resposta do cnpja sem nome do município (address.city nem address.municipality string).',
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function stringOuFalha(array $body, string $caminho, string $rotulo): string
    {
        $valor = data_get($body, $caminho);

        if (! is_string($valor) || $valor === '') {
            throw new RfbCnpjFalhouException(
                RfbCnpjStatus::Erro5xx,
                self::PROVEDOR,
                "Resposta do cnpja sem campo obrigatório: {$rotulo} ({$caminho}).",
            );
        }

        return $valor;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function stringOpcional(array $body, string $caminho): ?string
    {
        $valor = data_get($body, $caminho);

        if (! is_string($valor) || $valor === '') {
            return null;
        }

        return $valor;
    }

    private static function mapearSituacao(?string $texto): SituacaoCadastral
    {
        return match (strtolower(trim((string) $texto))) {
            'ativa' => SituacaoCadastral::Ativa,
            'suspensa' => SituacaoCadastral::Suspensa,
            'inapta' => SituacaoCadastral::Inapta,
            'baixada', 'nula' => SituacaoCadastral::Baixada,
            default => SituacaoCadastral::NaoInformada,
        };
    }

    private static function parseDataIso(?string $iso): ?Carbon
    {
        if ($iso === null) {
            return null;
        }

        try {
            return Carbon::parse($iso)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
