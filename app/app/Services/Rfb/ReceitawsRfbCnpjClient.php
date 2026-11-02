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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;

/**
 * Cliente real da RFB via receitaws.com.br (STORY-018 CA-3; IDR-004).
 *
 * Endpoint público canônico: `GET https://receitaws.com.br/v1/cnpj/{cnpj}`.
 *
 * Particularidade do provedor: erros chegam **com HTTP 200** e o discriminador
 * é o campo `status` no corpo (`"OK" | "ERROR"`). Por isso o parseSucesso
 * precisa fazer um pré-check do `status` antes de tocar nos demais campos —
 * `"CNPJ inválido"` é UX (cnpj_inexistente), `"Quota excedida"` (e demais) é
 * falha externa (erro_5xx).
 *
 * Mapeamento dos campos (resposta documentada em https://receitaws.com.br/):
 *
 *     {
 *       "status":   "OK",
 *       "nome":     "BANCO DO BRASIL SA",
 *       "fantasia": "",                    // string vazia em vez de null
 *       "abertura": "01/08/1966",          // formato BR dd/mm/YYYY
 *       "atividade_principal": [
 *         { "code": "64.22-1-00", "text": "..." }
 *       ],
 *       "municipio": "Brasilia",
 *       "uf":        "DF",
 *       "situacao":  "ATIVA"
 *     }
 */
final class ReceitawsRfbCnpjClient extends AbstractHttpRfbCnpjClient
{
    private const PROVEDOR = 'receitaws';

    protected function provedor(): string
    {
        return self::PROVEDOR;
    }

    protected function endpoint(string $cnpj): string
    {
        return "/cnpj/{$cnpj}";
    }

    protected function autenticarSeNecessario(PendingRequest $pending, string $apiKey): PendingRequest
    {
        // receitaws usa cabeçalho próprio no plano pago.
        return $pending->withHeaders(['Authorization' => 'Bearer '.$apiKey]);
    }

    protected function parseSucesso(Response $response): RfbCnpjResult
    {
        /** @var array<string, mixed> $body */
        $body = (array) $response->json();

        $status = is_string($body['status'] ?? null) ? strtoupper($body['status']) : '';

        if ($status === 'ERROR') {
            throw self::falhaCorpo200($body);
        }

        if ($status !== 'OK') {
            throw new RfbCnpjFalhouException(
                RfbCnpjStatus::Erro5xx,
                self::PROVEDOR,
                'Resposta do receitaws sem campo `status` reconhecível.',
            );
        }

        $razaoSocial = self::stringOuFalha($body, 'nome', 'razão social');
        $municipio = self::stringOuFalha($body, 'municipio', 'município');
        $uf = self::stringOuFalha($body, 'uf', 'UF');

        return new RfbCnpjResult(
            razaoSocial: $razaoSocial,
            nomeFantasia: self::stringOpcional($body, 'fantasia'),
            cnae: Atividade::normalizarCodigo($body['atividade_principal'][0]['code'] ?? null),
            municipio: $municipio,
            uf: strtoupper($uf),
            situacaoCadastral: self::mapearSituacao(self::stringOpcional($body, 'situacao')),
            dataFundacao: self::parseDataBr(self::stringOpcional($body, 'abertura')),
            fonteProvedor: self::PROVEDOR,
            consultadoAt: Carbon::now(),
            ficha: self::ficha($body, $municipio, strtoupper($uf)),
        );
    }

    /**
     * Ficha completa (STORY-063 CA-3/CA-10). Formato conferido contra a
     * resposta real em 2026-11-02 (fixture `sucesso-completo.json`). O QSA do
     * receitaws não traz documento nem data de entrada do sócio.
     *
     * @param  array<string, mixed>  $body
     */
    private static function ficha(array $body, string $municipio, string $uf): FichaRfb
    {
        $lista = static fn (mixed $v): array => is_array($v) ? array_values(array_filter($v, 'is_array')) : [];
        $principal = $lista($body['atividade_principal'] ?? null)[0] ?? [];
        $natureza = self::stringOpcional($body, 'natureza_juridica');

        return new FichaRfb(
            // "203-8 - Sociedade de Economia Mista" → só o texto, como no cnpja.
            naturezaJuridica: $natureza === null ? null : (preg_replace('/^[\d-]+\s*-\s*/', '', $natureza) ?? $natureza),
            porte: self::stringOpcional($body, 'porte'),
            capitalSocial: FichaRfb::normalizarCapital($body['capital_social'] ?? null),
            dataSituacao: DataRfb::normalizar($body['data_situacao'] ?? null),
            cnaePrincipal: Atividade::deBruto($principal['code'] ?? null, $principal['text'] ?? null),
            cnaesSecundarios: array_values(array_filter(array_map(
                static fn (array $a): ?Atividade => Atividade::deBruto($a['code'] ?? null, $a['text'] ?? null),
                $lista($body['atividades_secundarias'] ?? null),
            ))),
            endereco: Endereco::deBruto([
                'logradouro' => $body['logradouro'] ?? null,
                'numero' => $body['numero'] ?? null,
                'complemento' => $body['complemento'] ?? null,
                'bairro' => $body['bairro'] ?? null,
                'cep' => $body['cep'] ?? null,
                'municipio' => $municipio,
                'uf' => $uf,
            ]),
            socios: array_values(array_filter(array_map(
                static fn (array $q): ?Socio => Socio::deBruto($q['nome'] ?? null, $q['qual'] ?? null),
                $lista($body['qsa'] ?? null),
            ))),
            simples: self::regime($body['simples'] ?? null),
            simei: self::regime($body['simei'] ?? null),
        );
    }

    private static function regime(mixed $bruto): OpcaoRegime
    {
        if (! is_array($bruto)) {
            return new OpcaoRegime;
        }

        return OpcaoRegime::deBruto($bruto['optante'] ?? null, $bruto['data_opcao'] ?? null, $bruto['data_exclusao'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function falhaCorpo200(array $body): RfbCnpjFalhouException
    {
        $message = is_string($body['message'] ?? null) ? $body['message'] : '';
        $msgLower = mb_strtolower($message);

        // Mensagens conhecidas que devem ser tratadas como CNPJ inexistente (UX)
        // — receitaws responde tanto para DV inválido quanto para CNPJ não
        // cadastrado com mensagens similares.
        $ehInexistente = str_contains($msgLower, 'cnpj inv')
            || str_contains($msgLower, 'cnpj rejeitado')
            || str_contains($msgLower, 'cnpj n');

        $status = $ehInexistente
            ? RfbCnpjStatus::CnpjInexistente
            : RfbCnpjStatus::Erro5xx;

        $detalhe = $message !== '' ? $message : 'erro sem mensagem';

        return new RfbCnpjFalhouException(
            $status,
            self::PROVEDOR,
            "receitaws retornou status=ERROR ({$detalhe}).",
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
                "Resposta do receitaws sem campo obrigatório: {$rotulo} ({$caminho}).",
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
        return match (mb_strtolower(trim((string) $texto))) {
            'ativa' => SituacaoCadastral::Ativa,
            'suspensa' => SituacaoCadastral::Suspensa,
            'inapta' => SituacaoCadastral::Inapta,
            'baixada', 'nula' => SituacaoCadastral::Baixada,
            default => SituacaoCadastral::NaoInformada,
        };
    }

    private static function parseDataBr(?string $br): ?Carbon
    {
        if ($br === null) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $br)?->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
