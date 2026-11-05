<?php

declare(strict_types=1);

namespace App\Observabilidade;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Canal ÚNICO de alerta operacional para a equipe (ADR-004 §Decisão 1; STORY-086 CA-4 e CA-5, IDR-028).
 *
 * Todo alerta do produto sai por aqui — monitor da consulta de CNPJ, abuso de trial, falhas de cobrança —,
 * para a equipe ter um só lugar para olhar e para a política de conteúdo ficar num ponto só: o alerta
 * leva identificadores opacos e contagens, nunca dado pessoal nem de cartão (quem monta o contexto é
 * responsável por isso; {@see AlertaDeCobranca} filtra o que vem de `Log::error`).
 *
 * Tenta entregar pelo Telegram (Bot API HTTP); sem credenciais ou com a API fora, cai para um
 * `Log::warning` estruturado — o alerta nunca se perde em silêncio e nunca derruba quem o disparou.
 * O canal NUNCA registra em nível `error` (o ouvinte de erros de cobrança reagiria ao próprio alerta).
 */
class AlertaDaEquipe
{
    /**
     * @param  array<string, mixed>  $contexto
     */
    public function enviar(string $titulo, string $mensagem, array $contexto = []): void
    {
        // Última defesa: mesmo que quem monta o alerta deixe passar, e-mail, documento, cartão e IP saem mascarados.
        $titulo = self::higienizar($titulo);
        $mensagem = self::higienizar($mensagem);
        $contexto = array_map(fn (mixed $v): mixed => is_string($v) ? self::higienizar($v) : $v, $contexto);
        $token = (string) config('services.telegram.bot_token', '');
        $chatId = (string) config('services.telegram.chat_id', '');
        $prefixo = $this->prefixo();

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

                Log::info("{$prefixo}.alert.enviado", [
                    'module' => $prefixo,
                    'canal' => 'telegram',
                    'titulo' => $titulo,
                ] + $contexto);

                return;
            } catch (Throwable $e) {
                Log::warning("{$prefixo}.alert.telegram_falhou", [
                    'module' => $prefixo,
                    'canal' => 'telegram',
                    'erro' => $e::class,
                ] + $contexto);
                // Cai para o canal de log abaixo — alerta nunca perdido por silêncio.
            }
        }

        Log::warning("{$prefixo}.alert", [
            'module' => $prefixo,
            'canal' => 'log',
            'titulo' => $titulo,
            'mensagem' => $mensagem,
        ] + $contexto);
    }

    /**
     * Mascara o que nunca pode chegar à equipe por este canal: e-mail, CPF, CNPJ, número de cartão e o
     * último trecho de IP (STORY-086 CA-2). Identificadores opacos (UUID) e contagens passam intactos.
     */
    public static function higienizar(string $texto): string
    {
        $fora = '(?<![\w.-])';
        $fim = '(?![\w-])';
        $padroes = [
            '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/' => '[e-mail]',
            "/{$fora}\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}{$fim}/" => '[documento]',
            "/{$fora}\d{3}\.?\d{3}\.?\d{3}-?\d{2}{$fim}/" => '[documento]',
            "/{$fora}(?:\d[ -]?){13,19}{$fim}/" => '[cartão]',
        ];

        $texto = (string) preg_replace(array_keys($padroes), array_values($padroes), $texto);

        return (string) preg_replace_callback(
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b|(?<![\w:])(?:[0-9a-f]{1,4}:){3,7}[0-9a-f:]*/i',
            fn (array $m): string => self::mascararIp($m[0]),
            $texto,
        );
    }

    /** IPv4 sem o último octeto (`198.51.100.x`); IPv6 só com os três primeiros grupos (`2001:db8:abcd:x`). */
    public static function mascararIp(string $ip): string
    {
        if (str_contains($ip, ':')) {
            return implode(':', array_slice(explode(':', $ip), 0, 3)).':x';
        }

        return (string) preg_replace('/\.\d{1,3}$/', '.x', $ip);
    }

    /** Módulo que assina os logs do canal. O canal genérico usa `alerta`; especializações antigas mantêm o seu. */
    protected function prefixo(): string
    {
        return 'alerta';
    }
}
