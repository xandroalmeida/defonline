<?php

declare(strict_types=1);

namespace App\Services\Rfb;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Disparador de alerta operacional do monitor RFB (STORY-015 CA-5; ADR-004).
 *
 * Tenta entregar pelo Telegram (Bot API HTTP); cai para `Log::warning`
 * estruturado se as credenciais não estiverem configuradas — o EPIC-000 ainda
 * não tem o canal Telegram montado fim-a-fim, então este alerta começa
 * funcionando via log e migra para Telegram quando o token vier sem mudança
 * de código.
 */
class RfbAlerter
{
    /**
     * @param  array<string, mixed>  $contexto
     */
    public function enviar(string $titulo, string $mensagem, array $contexto = []): void
    {
        $token = (string) config('services.telegram.bot_token', '');
        $chatId = (string) config('services.telegram.chat_id', '');

        if ($token !== '' && $chatId !== '') {
            try {
                Http::timeout(3)->asJson()->post(
                    "https://api.telegram.org/bot{$token}/sendMessage",
                    [
                        'chat_id' => $chatId,
                        'text' => $titulo."\n\n".$mensagem,
                        'disable_web_page_preview' => true,
                    ],
                );

                Log::info('rfb.alert.enviado', [
                    'module' => 'rfb',
                    'canal' => 'telegram',
                    'titulo' => $titulo,
                ] + $contexto);

                return;
            } catch (Throwable $e) {
                Log::warning('rfb.alert.telegram_falhou', [
                    'module' => 'rfb',
                    'canal' => 'telegram',
                    'erro' => $e->getMessage(),
                ] + $contexto);
                // Cai para o canal de log abaixo — alerta nunca perdido por silêncio.
            }
        }

        Log::warning('rfb.alert', [
            'module' => 'rfb',
            'canal' => 'log',
            'titulo' => $titulo,
            'mensagem' => $mensagem,
        ] + $contexto);
    }
}
