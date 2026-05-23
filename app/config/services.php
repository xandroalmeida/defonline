<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Telegram — usado pelo RfbAlerter (STORY-015 CA-5) e por futuros alertas
    // operacionais (ADR-004). Token vazio = canal degrada para Log::warning.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'chat_id' => env('TELEGRAM_CHAT_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | RFB — Consulta de CNPJ (STORY-015 CA-6 / IDR-004 / IDR-005 / IDR-006)
    |--------------------------------------------------------------------------
    |
    | Schema canônico fixado pela IDR-004. Esta estória (STORY-015) entrega o
    | bloco completo com `provider=mock` default; a STORY-018 ativa os clientes
    | reais `cnpja` e `receitaws` sem nenhuma mudança aqui — apenas troca de
    | env (`RFB_PROVIDER=cnpja`, `RFB_CNPJA_API_KEY=...`).
    |
    | - `provider`: qual implementação será resolvida via container.
    | - `timeout`: 5s — UX exige resposta rápida (STORY-015 CA-2).
    | - `cache_ttl`: 300s (5 min) — mesmo CNPJ consultado 2× evita custo no
    |   provedor real. Mock IGNORA o cache (sempre retorna fresh) para não
    |   atrapalhar testes.
    | - `providers.{mock,cnpja,receitaws}`: base_url/api_key/rate_limit_per_minute
    |   já previstos mesmo vazios para evitar rework na STORY-018.
    */
    'rfb' => [
        'provider' => env('RFB_PROVIDER', 'mock'),
        'timeout' => (int) env('RFB_TIMEOUT', 5),
        'cache_ttl' => (int) env('RFB_CACHE_TTL', 300),
        'providers' => [
            'mock' => [
                'base_url' => null,
                'api_key' => null,
                'rate_limit_per_minute' => null,
            ],
            'cnpja' => [
                'base_url' => env('RFB_CNPJA_BASE_URL', 'https://api.cnpja.com'),
                'api_key' => env('RFB_CNPJA_API_KEY'),
                'rate_limit_per_minute' => (int) env('RFB_CNPJA_RATE_LIMIT_PER_MINUTE', 3),
            ],
            'receitaws' => [
                'base_url' => env('RFB_RECEITAWS_BASE_URL', 'https://receitaws.com.br/v1/cnpj'),
                'api_key' => env('RFB_RECEITAWS_API_KEY'),
                'rate_limit_per_minute' => (int) env('RFB_RECEITAWS_RATE_LIMIT_PER_MINUTE', 3),
            ],
        ],
    ],

];
