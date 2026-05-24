<?php

declare(strict_types=1);

namespace App\Services\Rfb;

use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\Rfb\RfbCnpjStatus;
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
            cnae: self::cnaeNormalizado($body['atividade_principal'][0]['code'] ?? null),
            municipio: $municipio,
            uf: strtoupper($uf),
            situacaoCadastral: self::mapearSituacao(self::stringOpcional($body, 'situacao')),
            dataFundacao: self::parseDataBr(self::stringOpcional($body, 'abertura')),
            fonteProvedor: self::PROVEDOR,
            consultadoAt: Carbon::now(),
        );
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

    private static function cnaeNormalizado(mixed $bruto): ?string
    {
        if ($bruto === null || $bruto === '') {
            return null;
        }

        $apenasDigitos = preg_replace('/\D+/', '', (string) $bruto) ?? '';

        return $apenasDigitos === '' ? null : str_pad($apenasDigitos, 7, '0', STR_PAD_LEFT);
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
