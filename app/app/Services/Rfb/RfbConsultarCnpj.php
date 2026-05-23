<?php

declare(strict_types=1);

namespace App\Services\Rfb;

use App\Domain\Cnpj;
use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\Rfb\RfbCnpjStatus;
use App\Observabilidade\AuditLogger;
use App\Support\RequestId;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orquestrador da consulta RFB (STORY-015 CA-3..CA-6).
 *
 * Empurra o {@see RfbCnpjClient} para uma boundary só, onde concentra:
 * - Validação prévia do DV (defesa em camadas além da do form Livewire).
 * - Cache por SHA-256 do CNPJ (CA-6) — mock ignora; provedores reais economizam.
 * - Métrica em `business_metrics` (CA-4) com `provider`/`status` em `meta`.
 * - Audit log `empresa.rfb_consultada` (CA-4) com hash do CNPJ.
 * - Nunca loga o CNPJ cru (PII — security-discipline.md).
 *
 * Quem chama (Livewire `Cadastrar`) recebe um {@see RfbCnpjResult} em sucesso,
 * ou {@see RfbCnpjFalhouException} em qualquer falha — e converte em fallback
 * transparente (CA-2).
 */
final class RfbConsultarCnpj
{
    public function __construct(private readonly RfbCnpjClient $client) {}

    /**
     * @throws RfbCnpjFalhouException quando a consulta não retorna sucesso.
     */
    public function executar(string $cnpj): RfbCnpjResult
    {
        $digitos = Cnpj::normalizar($cnpj);
        $provedor = (string) config('services.rfb.provider', 'mock');
        $hashCnpj = hash('sha256', $digitos);
        $chaveCache = "rfb:cnpj:{$hashCnpj}";
        $ttl = (int) config('services.rfb.cache_ttl', 300);
        $usaCache = $provedor !== 'mock' && $ttl > 0;

        if (! Cnpj::valido($digitos)) {
            // Tratamos como "inexistente" para que o caller só precise saber lidar com
            // RfbCnpjFalhouException — a fronteira de validação UX é do Livewire.
            $falha = new RfbCnpjFalhouException(
                RfbCnpjStatus::CnpjInexistente,
                $provedor,
                'CNPJ com DV inválido.',
            );
            $this->registrar($hashCnpj, $provedor, $falha->status, 0, false);
            throw $falha;
        }

        if ($usaCache) {
            /** @var array<string, mixed>|null $bruto */
            $bruto = Cache::get($chaveCache);
            if (is_array($bruto)) {
                return RfbCnpjResultSerializer::fromArray($bruto);
            }
        }

        $inicio = (int) (microtime(true) * 1000);

        try {
            $resultado = $this->client->consultarCnpj($digitos);
        } catch (RfbCnpjFalhouException $falha) {
            $duracao = ((int) (microtime(true) * 1000)) - $inicio;
            $this->registrar($hashCnpj, $provedor, $falha->status, $duracao, false);
            throw $falha;
        } catch (Throwable $erro) {
            // Qualquer exceção não-tipada do provedor real cai como erro_rede para
            // o monitoramento — STORY-018 pode refinar quando integrar HTTP de verdade.
            $duracao = ((int) (microtime(true) * 1000)) - $inicio;
            $envolvido = new RfbCnpjFalhouException(
                RfbCnpjStatus::ErroRede,
                $provedor,
                'Falha não classificada do provedor RFB.',
                $erro,
            );
            $this->registrar($hashCnpj, $provedor, $envolvido->status, $duracao, false);
            throw $envolvido;
        }

        $duracao = ((int) (microtime(true) * 1000)) - $inicio;
        $this->registrar($hashCnpj, $provedor, RfbCnpjStatus::Sucesso, $duracao, true);

        if ($usaCache) {
            Cache::put($chaveCache, RfbCnpjResultSerializer::toArray($resultado), $ttl);
        }

        return $resultado;
    }

    private function registrar(
        string $hashCnpj,
        string $provedor,
        RfbCnpjStatus $status,
        int $duracaoMs,
        bool $sucesso,
    ): void {
        $meta = [
            'provider' => $provedor,
            'status' => $status->value,
        ];

        try {
            // `inserido_em` cai no default `useCurrent` da migration. Deixamos o
            // banco carimbar para não cair na armadilha de timezone:
            // `now()` em PHP é UTC, mas DB::table()->insert() passa o valor sem
            // offset, e a sessão do Postgres ('America/Sao_Paulo' no compose)
            // o interpreta como local — jogando o instante 3h no futuro.
            DB::table('business_metrics')->insert([
                'request_id' => RequestId::get(),
                'tipo' => 'rfb_consulta',
                'sucesso' => $sucesso,
                'duracao_ms' => $duracaoMs,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        } catch (QueryException $e) {
            // Métrica não pode quebrar o fluxo de cadastro. Log estruturado para diagnose.
            Log::warning('rfb.metric.insert_failed', [
                'module' => 'rfb',
                'provider' => $provedor,
                'status' => $status->value,
                'erro' => $e->getMessage(),
            ]);
        }

        AuditLogger::log(
            action: 'empresa.rfb_consultada',
            subjectType: 'rfb_consulta',
            usuarioId: auth()->id() !== null ? (string) auth()->id() : null,
            actorType: auth()->id() !== null ? 'user' : 'system',
            actorId: auth()->id() !== null ? (string) auth()->id() : null,
            context: [
                'cnpj_sha256' => $hashCnpj,
                'provider' => $provedor,
                'status' => $status->value,
                'duracao_ms' => $duracaoMs,
                'sucesso' => $sucesso,
            ],
        );
    }
}
