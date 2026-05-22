<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Health checks da topologia (ADR-004 §1.4).
 *
 * - GET /health → liveness, < 100ms, NÃO toca Postgres.
 * - GET /ready  → readiness, SELECT 1 + check cache + queue config válida.
 *
 * Rotas públicas, sem auth/CSRF — consumidas por load balancer e monitor de uptime.
 */
final class HealthController
{
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'service' => Config::get('app.name'),
            'version' => Config::get('app.version', 'dev'),
            'env' => Config::get('app.env'),
        ]);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            $this->check('db', fn () => DB::connection()->select('select 1 as ok')),
            $this->check('cache', function () {
                Cache::put('ready:check', 'ok', 5);
                $value = Cache::get('ready:check');
                if ($value !== 'ok') {
                    throw new \RuntimeException('cache_round_trip_failed');
                }
            }),
            $this->check('queue', function () {
                $driver = Config::get('queue.default');
                if (! is_string($driver) || $driver === '') {
                    throw new \RuntimeException('queue_driver_misconfigured');
                }
            }),
        ];

        $allOk = collect($checks)->every(fn (array $c) => $c['ok']);

        return new JsonResponse(
            [
                'status' => $allOk ? 'ok' : 'degraded',
                'service' => Config::get('app.name'),
                'version' => Config::get('app.version', 'dev'),
                'env' => Config::get('app.env'),
                'checks' => $checks,
            ],
            $allOk ? 200 : 503
        );
    }

    /**
     * @return array{name: string, ok: bool, error?: string}
     */
    private function check(string $name, callable $probe): array
    {
        try {
            $probe();

            return ['name' => $name, 'ok' => true];
        } catch (Throwable $e) {
            return ['name' => $name, 'ok' => false, 'error' => $e->getMessage()];
        }
    }
}
