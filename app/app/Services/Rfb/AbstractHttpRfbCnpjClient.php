<?php

declare(strict_types=1);

namespace App\Services\Rfb;

use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\Rfb\RfbCnpjStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Base HTTP comum aos provedores reais da RFB (STORY-018; IDR-004).
 *
 * Concentra:
 * - Rate-limit por provedor (`rfb:provider:{provedor}`) com fail-fast — se
 *   o slot não está disponível, falha com {@see RfbCnpjStatus::Erro5xx} sem
 *   esperar o decay (CA-5 § "falhar rápido, não esperar 60s").
 * - Timeout curto compartilhado (`config('services.rfb.timeout')`, default 5s).
 * - Classificação canônica de erros HTTP em {@see RfbCnpjStatus} para que o
 *   monitoramento (CA-6, comando `MonitorarRfbErrorRate`) trate todos os
 *   provedores uniformemente.
 *
 * Cada subclasse só precisa decidir:
 * - {@see provedor()} — chave usada em config, métrica e rate-limit.
 * - {@see endpoint()} — sub-caminho a partir da `base_url` configurada.
 * - {@see parseSucesso()} — mapeamento JSON → DTO (e detecção de "erro no
 *   corpo 200" como o `status: ERROR` do receitaws).
 * - {@see autenticarSeNecessario()} — opcional; default usa header padrão
 *   `Authorization: <api_key>` (sobrescrito por provedores com esquema próprio).
 */
abstract class AbstractHttpRfbCnpjClient implements RfbCnpjClient
{
    abstract protected function provedor(): string;

    abstract protected function endpoint(string $cnpj): string;

    abstract protected function parseSucesso(Response $response): RfbCnpjResult;

    public function consultarCnpj(string $cnpj): RfbCnpjResult
    {
        $provedor = $this->provedor();
        $rpm = $this->rpm();

        $resultado = RateLimiter::attempt(
            "rfb:provider:{$provedor}",
            $rpm,
            fn (): RfbCnpjResult => $this->fazerRequisicao($cnpj),
            60,
        );

        if ($resultado === false) {
            // Slot indisponível no minuto corrente. Tratado como Erro5xx (falha
            // do provedor, não input ruim) — fallback transparente da STORY-015
            // absorve a UX.
            throw new RfbCnpjFalhouException(
                RfbCnpjStatus::Erro5xx,
                $provedor,
                "Rate-limit do provedor {$provedor} estourado (>{$rpm}/min).",
            );
        }

        return $resultado;
    }

    protected function autenticarSeNecessario(PendingRequest $pending, string $apiKey): PendingRequest
    {
        // cnpja.com aceita `Authorization: <token>` direto (sem "Bearer"). receitaws
        // usa `X-Token` — sobrescreve este método. Default cobre o caso mais comum.
        return $pending->withHeaders(['Authorization' => $apiKey]);
    }

    final protected function rpm(): int
    {
        $rpm = (int) config(
            "services.rfb.providers.{$this->provedor()}.rate_limit_per_minute",
            3,
        );

        return $rpm > 0 ? $rpm : 3;
    }

    final protected function baseUrl(): string
    {
        return rtrim(
            (string) config("services.rfb.providers.{$this->provedor()}.base_url", ''),
            '/',
        );
    }

    final protected function apiKey(): string
    {
        return (string) config("services.rfb.providers.{$this->provedor()}.api_key", '');
    }

    private function fazerRequisicao(string $cnpj): RfbCnpjResult
    {
        $timeout = (int) config('services.rfb.timeout', 5);
        $provedor = $this->provedor();
        $apiKey = $this->apiKey();

        $pending = Http::timeout($timeout)
            ->connectTimeout($timeout)
            ->acceptJson();

        if ($apiKey !== '') {
            $pending = $this->autenticarSeNecessario($pending, $apiKey);
        }

        try {
            $response = $pending->get($this->baseUrl().$this->endpoint($cnpj));
        } catch (ConnectionException $e) {
            $status = self::ehTimeout($e) ? RfbCnpjStatus::Timeout : RfbCnpjStatus::ErroRede;

            throw new RfbCnpjFalhouException($status, $provedor, $e->getMessage(), $e);
        }

        $http = $response->status();

        // 404 do provedor = CNPJ não localizado na base. Tratado como UX
        // ("CNPJ inexistente") em vez de incidente operacional.
        if ($http === 404) {
            throw new RfbCnpjFalhouException(
                RfbCnpjStatus::CnpjInexistente,
                $provedor,
                "Provedor {$provedor} retornou 404 para o CNPJ informado.",
            );
        }

        // 429: rate-limit do PRÓPRIO provedor (cota da conta), não o nosso.
        // Classificado como Erro5xx — falha externa que aciona o fallback.
        if ($http === 429 || $http >= 500) {
            throw new RfbCnpjFalhouException(
                RfbCnpjStatus::Erro5xx,
                $provedor,
                "Provedor {$provedor} retornou HTTP {$http}.",
            );
        }

        if (! $response->successful()) {
            throw new RfbCnpjFalhouException(
                RfbCnpjStatus::Erro5xx,
                $provedor,
                "Provedor {$provedor} respondeu HTTP {$http} (inesperado).",
            );
        }

        return $this->parseSucesso($response);
    }

    private static function ehTimeout(ConnectionException $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'timed out')
            || str_contains($msg, 'timeout')
            || str_contains($msg, 'operation timed out');
    }
}
