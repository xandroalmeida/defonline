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

    // Telegram — canal ÚNICO de alerta da equipe (AlertaDaEquipe: monitor RFB, abuso de trial,
    // falhas de cobrança — STORY-015, STORY-086; ADR-004). Token vazio = canal degrada para Log::warning.
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
                // STORY-018 — smoke contratual em 2026-05-23 confirmou que `api.cnpja.com`
                // exige autenticação (HTTP 401 sem token). O endpoint público gratuito
                // (Open API, 3 RPM) vive em `open.cnpja.com`. O `api.cnpja.com` continua
                // sendo o endpoint do plano pago — para ativá-lo, sobrescreva via env
                // junto com `RFB_CNPJA_API_KEY`.
                'base_url' => env('RFB_CNPJA_BASE_URL', 'https://open.cnpja.com'),
                'api_key' => env('RFB_CNPJA_API_KEY'),
                'rate_limit_per_minute' => (int) env('RFB_CNPJA_RATE_LIMIT_PER_MINUTE', 3),
            ],
            'receitaws' => [
                // IDR-004: base_url = https://receitaws.com.br/v1 (sem o sub-recurso /cnpj,
                // que é montado pelo ReceitawsRfbCnpjClient antes do CNPJ).
                'base_url' => env('RFB_RECEITAWS_BASE_URL', 'https://receitaws.com.br/v1'),
                'api_key' => env('RFB_RECEITAWS_API_KEY'),
                'rate_limit_per_minute' => (int) env('RFB_RECEITAWS_RATE_LIMIT_PER_MINUTE', 3),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagamentos (STORY-076 / ADR-010)
    |--------------------------------------------------------------------------
    |
    | - `provider`: `fake` (local/testes, sem rede) ou `asaas`. O `fake` é recusado em produção.
    | - Asaas: host e chave vêm SEMPRE do ambiente, sem padrão de produção. O sandbox é
    |   `https://api-sandbox.asaas.com/v3` com chave `$aact_hmlg_...`; produção é `https://api.asaas.com/v3`
    |   com `$aact_prod_...`. O adaptador recusa prefixo de chave que não bate com o host.
    | - `webhook_token`: segredo do cabeçalho `asaas-access-token` (32-255 caracteres, diferente da chave).
    */
    'payments' => [
        // URL do webhook cadastrada no provedor. Sem valor, é a do próprio app (produção).
        'webhook_url' => env('PAYMENTS_WEBHOOK_URL'),
        // Sem valor: `fake` fora de produção; em produção, nenhum provedor (a cobrança fica desligada, STORY-093).
        'provider' => env('PAYMENTS_PROVIDER'),
        'pix_expiracao_minutos' => (int) env('PIX_EXPIRACAO_MINUTOS', 30),
        // Validade do link do checkout de assinatura com cartão (o provedor aceita 10 a 1440).
        'cartao_expiracao_minutos' => (int) env('CARTAO_EXPIRACAO_MINUTOS', 60),
        'webhook_token' => env('PAYMENTS_WEBHOOK_TOKEN'),
        // STORY-081 CA-1 — a partir de quantos dias do vencimento o lote de créditos ganha o alerta.
        'lote_perto_de_vencer_dias' => (int) env('LOTE_PERTO_DE_VENCER_DIAS', 30),
        // STORY-083 / ADR-010 4.5 — menor cobrança que o provedor aceita (R$ 5,00 no sandbox do Asaas). Abaixo disso a
        // contratação é ativada sem cobrança.
        'valor_minimo_centavos' => (int) env('PAGAMENTO_VALOR_MINIMO_CENTAVOS', 500),
    ],

    'asaas' => [
        'base_url' => env('ASAAS_BASE_URL'),
        'api_key' => env('ASAAS_API_KEY') ?: env('ASAAS_SANDBOX_API_KEY'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'timeout' => (int) env('ASAAS_TIMEOUT', 30),
    ],

];
