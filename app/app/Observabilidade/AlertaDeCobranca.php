<?php

declare(strict_types=1);

namespace App\Observabilidade;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;

/**
 * STORY-086 CA-5 / IDR-028 — liga os `Log::error` do módulo de cobrança ao canal único da equipe
 * ({@see AlertaDaEquipe}). As estórias 076 a 085 já marcam o que precisa de olhar humano com
 * `Log::error(..., ['module' => 'cobranca', ...])`; em vez de repetir uma chamada de alerta em cada
 * ponto (e esquecer a próxima), um ouvinte do próprio log faz a ponte. Quem escreve um novo
 * `Log::error` com `module = cobranca` já alerta, sem mais nada.
 *
 * **Sem dado pessoal nem de cartão (CA-2).** Do contexto do log só passam identificadores opacos
 * (`*_id`), contagens, o tipo e a ação, nomes de classe de exceção e valores em centavos. Texto livre
 * (`motivo`, `message`) NUNCA vai: pode carregar a resposta do provedor ou o texto de uma exceção.
 *
 * **Silêncio de 30 minutos** por falha (ADR-004): o mesmo erro com os mesmos identificadores não gera
 * dezenas de mensagens — o log completo continua inteiro, só o aviso à equipe é espaçado.
 */
final class AlertaDeCobranca
{
    private const NIVEIS = ['error', 'critical', 'alert', 'emergency'];

    /** Chaves de contexto (além de `*_id`) que podem acompanhar o alerta. */
    private const CHAVES_SEGURAS = ['action', 'tipo', 'modo', 'passo', 'tentativa', 'status', 'esperado_centavos', 'recebido_centavos', 'exception', 'erro'];

    /** Chaves que o próprio contexto de log (middleware) injeta em toda linha: seguem no alerta, fora da chave do silêncio. */
    private const DO_AMBIENTE = ['request_id', 'user_id'];

    private static bool $emAndamento = false;

    public function __construct(private readonly AlertaDaEquipe $canal) {}

    public function handle(MessageLogged $log): void
    {
        if (self::$emAndamento || ! in_array($log->level, self::NIVEIS, true) || ($log->context['module'] ?? null) !== 'cobranca') {
            return;
        }

        $contexto = self::contextoSeguro($log->context);
        // O silêncio é por falha: o que o middleware acrescenta a cada requisição não a distingue (nem deve a repetir).
        $chave = 'alerta-cobranca:'.sha1($log->message.'|'.json_encode(array_diff_key($contexto, array_flip(self::DO_AMBIENTE))));

        if (! Cache::add($chave, 1, now()->addMinutes((int) config('cobranca.observabilidade.silencio_minutos', 30)))) {
            return;
        }

        $prioridade = ($log->context['prioridade'] ?? 'P2') === 'P1' ? 'P1' : 'P2';
        $linhas = ["Ocorrência: {$log->message}"];
        foreach ($contexto as $campo => $valor) {
            $linhas[] = "{$campo}: {$valor}";
        }

        self::$emAndamento = true;

        try {
            $this->canal->enviar("[{$prioridade}] Cobrança: {$log->message}", implode("\n", $linhas), $contexto);
        } finally {
            self::$emAndamento = false;
        }
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return array<string, string|int|float|bool>
     */
    public static function contextoSeguro(array $contexto): array
    {
        $seguro = [];

        foreach ($contexto as $campo => $valor) {
            $campo = (string) $campo;
            $ehId = preg_match('/^[a-z0-9_]+_id$/', $campo) === 1;

            if (! $ehId && ! in_array($campo, self::CHAVES_SEGURAS, true)) {
                continue;
            }

            // Identificador e nome de classe: só caracteres de identificador (nada de e-mail ou frase solta).
            if (is_string($valor) && preg_match('/^[A-Za-z0-9_.:\\\\-]{1,100}$/', $valor) === 1 && ! str_contains($valor, '@')) {
                $seguro[$campo] = $valor;
            } elseif (is_int($valor) || is_float($valor) || is_bool($valor)) {
                $seguro[$campo] = $valor;
            }
        }

        ksort($seguro);

        return $seguro;
    }
}
