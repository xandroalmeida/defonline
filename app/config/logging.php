<?php

use App\Observabilidade\LogSanitizer;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

/*
 * Logging do DEFOnline — ADR-004.
 *
 * Driver `stack` com dois canais paralelos:
 *  - `stdout` — JsonFormatter, consumido por `docker logs` em todos os ambientes.
 *  - `daily`  — JsonFormatter, retenção 90 dias (RNF §6.1).
 *
 * `LogSanitizer` aplicado via `tap` em ambos os canais — único ponto de mascaramento
 * de PII em log (ADR-003 §LogSanitizer + ADR-004 §1.1).
 */

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'stdout,daily')),
            'ignore_exceptions' => false,
        ],

        'stdout' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stdout',
            ],
            'formatter' => JsonFormatter::class,
            'processors' => [PsrLogMessageProcessor::class],
            'tap' => [LogSanitizer::class],
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => (int) env('LOG_DAILY_DAYS', 90),
            'formatter' => JsonFormatter::class,
            'replace_placeholders' => true,
            'tap' => [LogSanitizer::class],
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'formatter' => JsonFormatter::class,
            'replace_placeholders' => true,
            'tap' => [LogSanitizer::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => JsonFormatter::class,
            'processors' => [PsrLogMessageProcessor::class],
            'tap' => [LogSanitizer::class],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
