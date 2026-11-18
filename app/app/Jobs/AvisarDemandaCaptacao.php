<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\DemandaCaptacaoEquipeMessage;
use App\Mail\DemandaCaptacaoRecebidaMessage;
use App\Models\CaptacaoConfig;
use App\Models\DemandaCaptacao;
use App\Models\Diagnostico;
use App\Observabilidade\AlertaDaEquipe;
use App\Support\DataExibicao;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-102 / PDR-021 — avisa a equipe e o cliente de uma demanda de captação.
 *
 * Só age quando o cliente autorizou o contato. A equipe recebe e-mail apenas se houver um cadastrado
 * no backoffice; o Telegram da equipe recebe só o id da demanda (o canal não leva dado pessoal).
 * Cada aviso grava a hora em que saiu: uma nova tentativa do job não repete o que já foi enviado.
 */
final class AvisarDemandaCaptacao extends BaseJob
{
    public int $tries = 3;

    public function __construct(public readonly string $demandaId)
    {
        parent::__construct();
    }

    protected function handleJob(): void
    {
        $demanda = DemandaCaptacao::query()->with(['usuario', 'empresa'])->find($this->demandaId);

        if ($demanda === null || ! $demanda->autoriza_contato) {
            return;
        }

        $prazo = DataExibicao::dia($demanda->prazoRetorno());

        if ($demanda->equipe_avisada_em === null) {
            $this->avisarEquipe($demanda, $prazo);
        }

        if ($demanda->cliente_avisado_em === null) {
            Mail::to($demanda->usuario->email)->send(new DemandaCaptacaoRecebidaMessage(
                nome: $demanda->usuario->primeiroNome(),
                empresa: $demanda->empresa->nomeExibicao(),
            ));
            $demanda->forceFill(['cliente_avisado_em' => now()])->save();
        }
    }

    private function avisarEquipe(DemandaCaptacao $demanda, string $prazo): void
    {
        $email = CaptacaoConfig::emailDaEquipe();

        if ($email !== null) {
            $diagnostico = Diagnostico::query()->withoutGlobalScopes()->find($demanda->diagnostico_id);
            $payload = $diagnostico === null ? [] : (array) $diagnostico->quiz_payload;

            Mail::to($email)->send(new DemandaCaptacaoEquipeMessage(
                empresa: $demanda->empresa->nomeExibicao(),
                documento: $demanda->empresa->documentoFormatado(),
                usuarioNome: $demanda->usuario->nome,
                usuarioEmail: $demanda->usuario->email,
                usuarioTelefone: $demanda->usuario->telefone,
                empresaEmail: $demanda->empresa->email,
                empresaTelefone: $demanda->empresa->telefoneFormatado(),
                valorCaptar: self::moeda($payload['Q18'] ?? null),
                endividamento: self::moeda($payload['Q19'] ?? null),
                vendaCartao: self::moeda($payload['Q20'] ?? null),
                socios: array_values(array_map(
                    fn (mixed $s): string => is_array($s) ? (string) ($s['nome'] ?? '') : '',
                    (array) ($payload['socios'] ?? []),
                )),
                prazo: $prazo,
                link: route('backoffice.captacao'),
            ));
        }

        app(AlertaDaEquipe::class)->enviar(
            'Nova demanda de captação',
            'Retorno ao cliente até '.$prazo.'. Detalhes no backoffice, em Captação.',
            ['demanda_id' => $demanda->id],
        );

        $demanda->forceFill(['equipe_avisada_em' => now()])->save();
    }

    private static function moeda(mixed $valor): ?string
    {
        return is_numeric($valor) ? 'R$ '.number_format((float) $valor, 2, ',', '.') : null;
    }
}
