<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\DescontoDaAssinatura;
use App\Domain\Cobranca\StatusAssinatura;
use App\Mail\FimDoDescontoMessage;
use App\Models\Assinatura;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * STORY-083 CA-8 — e-mail 7 dias antes do fim do desconto. Um por desconto (`desconto_aviso_em`, que só é
 * gravado depois do envio: se o e-mail falhar, a rotina horária tenta de novo). A mesma janela alimenta
 * o aviso em Meu plano ({@see DescontoDaAssinatura::pertoDoFim()}).
 */
final class AvisarFimDoDesconto
{
    public const DIAS = 7;

    /** @return array{candidatas: int, enviados: int, falhas: int} */
    public function executar(?Closure $progresso = null): array
    {
        $agora = CarbonImmutable::now();
        $candidatas = Assinatura::query()
            ->where('trial', false)
            ->whereIn('status', [StatusAssinatura::Ativa->value, StatusAssinatura::Cancelada->value])
            ->where('desconto_centavos', '>', 0)
            ->whereNotNull('desconto_periodos')
            ->whereNull('desconto_aviso_em')
            ->get()
            ->filter(fn (Assinatura $a): bool => $a->desconto()?->pertoDoFim(CicloCobranca::from((string) $a->ciclo), $a->data_inicio, $agora, self::DIAS) === true)
            ->values();

        $enviados = 0;
        $falhas = 0;

        foreach ($candidatas as $i => $a) {
            $this->avisar($a) ? $enviados++ : $falhas++;

            if ($progresso !== null) {
                $progresso($i + 1, $candidatas->count(), $enviados, $falhas);
            }
        }

        return ['candidatas' => $candidatas->count(), 'enviados' => $enviados, 'falhas' => $falhas];
    }

    private function avisar(Assinatura $a): bool
    {
        $usuario = Usuario::query()->find($a->usuario_id);
        $desconto = $a->desconto();

        if ($usuario === null || $desconto === null) {
            return false;
        }

        $ciclo = CicloCobranca::from((string) $a->ciclo);
        $fim = $desconto->fim($ciclo, $a->data_inicio);

        try {
            Mail::to($usuario->email)->send(new FimDoDescontoMessage(
                $usuario->primeiroNome(),
                $fim?->setTimezone('America/Sao_Paulo')->format('d/m/Y') ?? '',
                'R$ '.number_format($desconto->cheio / 100, 2, ',', '.').' '.$ciclo->porExtenso(),
                $a->id_externo === null && ! $a->pagoComPix(),
                route('meu-plano'),
            ));
            $a->forceFill(['desconto_aviso_em' => CarbonImmutable::now()])->save();

            return true;
        } catch (Throwable $e) {
            Log::error('cobranca.aviso_fim_do_desconto.falha', ['module' => 'cobranca', 'assinatura_id' => $a->id, 'exception' => $e::class, 'message' => $e->getMessage()]);

            return false;
        }
    }
}
