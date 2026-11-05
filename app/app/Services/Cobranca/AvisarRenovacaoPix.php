<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\RenovacaoPix;
use App\Domain\Cobranca\StatusAssinatura;
use App\Mail\RenovacaoPixMessage;
use App\Models\Assinatura;
use App\Models\Plano;
use App\Models\Usuario;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * STORY-080 CA-4 — avisa, 7 dias antes do fim, quem tem plano anual pago com Pix: o plano não renova
 * sozinho e o e-mail leva ao pagamento da renovação. Um aviso por ciclo: `aviso_renovacao_pix_para`
 * guarda o vencimento avisado e só é gravado depois do envio (falha tenta de novo na hora seguinte).
 */
final class AvisarRenovacaoPix
{
    /** @return array{candidatas: int, enviados: int, falhas: int} */
    public function executar(?Closure $progresso = null): array
    {
        $agora = now();
        $candidatas = Assinatura::query()
            ->where('trial', false)
            ->where('status', StatusAssinatura::Ativa->value)
            ->where('meio_pagamento', 'pix')
            ->where('ciclo', CicloCobranca::Anual->value)
            ->where('proximo_vencimento', '>', $agora)
            ->where('proximo_vencimento', '<=', $agora->copy()->addDays(RenovacaoPix::AVISO_DIAS))
            ->orderBy('proximo_vencimento')
            ->get();

        $enviados = 0;
        $falhas = 0;

        foreach ($candidatas as $i => $assinatura) {
            // STORY-083: o próximo ano é gratuito (desconto), a renovação é automática: não há o que pagar.
            $gratuita = $assinatura->desconto()?->gratuito($assinatura->periodos_pagos + 1) === true;

            if (! $gratuita && RenovacaoPix::avisoDevido($assinatura->proximo_vencimento, $assinatura->aviso_renovacao_pix_para, $agora)) {
                if ($this->avisar($assinatura)) {
                    $enviados++;
                } else {
                    $falhas++;
                }
            }

            if ($progresso !== null) {
                $progresso($i + 1, $candidatas->count(), $enviados, $falhas);
            }
        }

        return ['candidatas' => $candidatas->count(), 'enviados' => $enviados, 'falhas' => $falhas];
    }

    private function avisar(Assinatura $assinatura): bool
    {
        $usuario = Usuario::query()->find($assinatura->usuario_id);
        $plano = $assinatura->plano_id === null ? null : Plano::query()->find($assinatura->plano_id);

        if ($usuario === null || $plano === null) {
            return false;
        }

        $fuso = 'America/Sao_Paulo';

        try {
            Mail::to($usuario->email)->send(new RenovacaoPixMessage(
                $usuario->primeiroNome(),
                $plano->nome,
                $assinatura->proximo_vencimento->copy()->setTimezone($fuso)->format('d/m/Y'),
                RenovacaoPix::novoVencimento($assinatura->data_inicio, $assinatura->periodos_pagos)->setTimezone($fuso)->format('d/m/Y'),
                'R$ '.number_format(((int) $assinatura->preco_snapshot_centavos) / 100, 2, ',', '.'),
                route('assinatura.renovar'),
            ));
            $assinatura->forceFill(['aviso_renovacao_pix_para' => $assinatura->proximo_vencimento])->save();

            return true;
        } catch (Throwable $e) {
            Log::error('cobranca.aviso_renovacao_pix.falha', ['module' => 'cobranca', 'assinatura_id' => $assinatura->id, 'exception' => $e::class, 'message' => $e->getMessage()]);

            return false;
        }
    }
}
