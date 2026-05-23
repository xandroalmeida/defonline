<?php

declare(strict_types=1);

namespace App\Services\Rfb;

use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\SituacaoCadastral;
use Illuminate\Support\Carbon;

/**
 * Serialização do {@see RfbCnpjResult} para cache (STORY-015 CA-6).
 *
 * Vive separado do DTO porque o DTO é puro (sem dependência de Carbon::parse
 * nem do cast de enum) — fica em `app/Domain/Rfb` e entra no gate de
 * cobertura ≥98%. Esta camada faz a ponte com o cache key/value.
 */
final class RfbCnpjResultSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(RfbCnpjResult $resultado): array
    {
        return [
            'razao_social' => $resultado->razaoSocial,
            'nome_fantasia' => $resultado->nomeFantasia,
            'cnae' => $resultado->cnae,
            'municipio' => $resultado->municipio,
            'uf' => $resultado->uf,
            'situacao_cadastral' => $resultado->situacaoCadastral->value,
            'data_fundacao' => $resultado->dataFundacao?->toIso8601String(),
            'fonte_provedor' => $resultado->fonteProvedor,
            'consultado_at' => $resultado->consultadoAt->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function fromArray(array $dados): RfbCnpjResult
    {
        return new RfbCnpjResult(
            razaoSocial: (string) $dados['razao_social'],
            nomeFantasia: isset($dados['nome_fantasia']) ? (string) $dados['nome_fantasia'] : null,
            cnae: isset($dados['cnae']) ? (string) $dados['cnae'] : null,
            municipio: (string) $dados['municipio'],
            uf: (string) $dados['uf'],
            situacaoCadastral: SituacaoCadastral::from((string) $dados['situacao_cadastral']),
            dataFundacao: isset($dados['data_fundacao']) ? Carbon::parse((string) $dados['data_fundacao']) : null,
            fonteProvedor: (string) $dados['fonte_provedor'],
            consultadoAt: Carbon::parse((string) $dados['consultado_at']),
        );
    }
}
