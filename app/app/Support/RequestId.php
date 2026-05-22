<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Container global do request_id (ADR-002).
 *
 * Web: middleware AssignRequestId chama RequestId::set() no início do request.
 * Worker: BaseJob lê meta.request_id do payload e chama RequestId::set() no handle().
 * Scheduler: cada tarefa cron gera seu próprio request_id (prefixo `sched:`).
 */
final class RequestId
{
    private static ?string $value = null;

    public static function set(string $id): void
    {
        self::$value = $id;
    }

    public static function get(): string
    {
        if (self::$value === null) {
            self::$value = (string) Str::uuid7();
        }

        return self::$value;
    }

    public static function reset(): void
    {
        self::$value = null;
    }

    /**
     * Gera UUID v7 ordenável por tempo (default da ADR-002).
     */
    public static function generate(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * Valida que a string tem formato UUID v7 ou prefixo `sched:` aceito pela convenção.
     */
    public static function isValid(string $candidate): bool
    {
        if (str_starts_with($candidate, 'sched:')) {
            $candidate = substr($candidate, 6);
        }

        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $candidate,
        );
    }
}
