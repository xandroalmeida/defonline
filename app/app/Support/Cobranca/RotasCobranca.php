<?php

declare(strict_types=1);

namespace App\Support\Cobranca;

use Illuminate\Support\Facades\Route;

/**
 * STORY-075 — destinos da página de planos que nascem em outras estórias (checkout, troca de
 * plano, teste). Como em {@see TextoBloqueio}, enquanto a rota não existe a ação some da tela
 * em vez de quebrar; quem criar a rota usa o nome abaixo e o botão aparece.
 */
final class RotasCobranca
{
    public const ASSINAR = 'checkout.assinatura';   // STORY-077: parâmetros plano e ciclo

    public const COMPRAR = 'creditos.comprar';      // STORY-076: parâmetro pacote

    public const TROCAR = 'plano.trocar';           // STORY-082: parâmetros plano e ciclo

    public const TESTE = 'trial.iniciar';           // STORY-074

    public const MEU_PLANO = 'meu-plano';           // STORY-081: destino dos e-mails de renovação e de falha de cobrança

    /** @param  array<string, string>  $parametros */
    public static function url(string $rota, array $parametros = []): ?string
    {
        return Route::has($rota) ? route($rota, $parametros) : null;
    }
}
