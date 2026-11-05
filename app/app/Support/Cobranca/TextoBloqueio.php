<?php

declare(strict_types=1);

namespace App\Support\Cobranca;

use App\Domain\Cobranca\Bloqueio;
use App\Domain\Cobranca\MotivoBloqueio;
use Illuminate\Support\Facades\Route;

/**
 * STORY-073 CA-11 — o texto de cada causa de bloqueio e as ações possíveis, conforme a spec
 * `STORY-070-bloqueio-sem-saldo` §4. Microcopy provisória (a spec manda sobre este arquivo).
 *
 * As ações levam a telas que nascem em outras estórias. Enquanto a rota não existe, a ação some
 * da tela em vez de quebrar (spec §9). Quem criar a rota usa o nome abaixo e a ação aparece.
 */
final class TextoBloqueio
{
    /** Chave da ação => nome da rota que a atende. */
    public const ROTAS = [
        'trial' => 'trial.iniciar',          // STORY-074
        'planos' => 'planos',                // STORY-075
        'trocar-plano' => 'planos',          // STORY-075
        'creditos' => 'creditos.comprar',    // STORY-076
        'cartao' => 'cartao.atualizar',      // STORY-079
        'cadastrar-cartao' => 'cartao.atualizar', // STORY-079 / 083
        'meu-plano' => 'meu-plano',          // STORY-081
    ];

    private const ROTULOS = [
        'trial' => 'Começar teste grátis',
        'planos' => 'Ver planos',
        'trocar-plano' => 'Trocar de plano',
        'creditos' => 'Comprar créditos',
        'cartao' => 'Atualizar cartão',
        'cadastrar-cartao' => 'Cadastrar cartão',
        'meu-plano' => 'Ver meu plano',
    ];

    /**
     * @return array{titulo: string, causa: string, acoes: list<array{chave: string, rotulo: string}>}
     */
    public static function para(Bloqueio $b): array
    {
        $data = $b->data?->setTimezone('America/Sao_Paulo')->format('d/m/Y') ?? '';

        [$titulo, $causa, $acoes] = match ($b->motivo) {
            MotivoBloqueio::SemNada => $b->trialElegivel
                ? ['Você ainda não tem análises', 'Para fazer uma análise, comece o teste grátis ou escolha um plano.', ['trial', 'planos']]
                : ['Você ainda não tem análises', 'Escolha um plano ou compre créditos para fazer uma análise.', ['planos', 'creditos']],
            MotivoBloqueio::TesteUsado => ['Você usou a análise do teste', "O teste termina em {$data}. Assine ou compre créditos para fazer outra análise.", ['planos', 'creditos']],
            MotivoBloqueio::TesteEncerrado => ['Seu teste terminou', "Seu teste terminou em {$data}. Seus diagnósticos continuam disponíveis.", ['planos', 'creditos']],
            MotivoBloqueio::TesteEmpresaJaAnalisada => ['Esta empresa já foi analisada em um teste', 'Esta empresa já foi analisada em um teste gratuito, então não conseguimos incluir outro teste para ela.', ['planos', 'creditos']],
            MotivoBloqueio::CotaEsgotada => [
                'Você usou as análises deste ciclo',
                $b->renova
                    ? "Suas {$b->cotaDoCiclo} análises do ciclo acabaram. Novas análises em {$data}."
                    : "Suas {$b->cotaDoCiclo} análises do ciclo acabaram. Seu plano termina em {$data}.",
                ['creditos', 'trocar-plano'],
            ],
            MotivoBloqueio::CreditosVencidos => ['Seus créditos venceram', "Os créditos venceram em {$data} e não podem mais ser usados.", ['creditos', 'planos']],
            MotivoBloqueio::EmAtraso => ['Sua assinatura está em atraso', 'Não conseguimos cobrar a renovação. Atualize o cartão para voltar a fazer análises.', ['cartao', 'meu-plano']],
            MotivoBloqueio::PlanoEncerrado => ['Seu plano terminou', "Seu plano terminou em {$data}. Seus diagnósticos continuam disponíveis.", ['planos', 'creditos']],
            MotivoBloqueio::DescontoAcabouSemCartao => ['Falta cadastrar um cartão', "O desconto terminou em {$data}. Cadastre um cartão para continuar.", ['cadastrar-cartao', 'meu-plano']],
        };

        return [
            'titulo' => $titulo,
            'causa' => $causa,
            'acoes' => array_map(fn (string $chave): array => ['chave' => $chave, 'rotulo' => self::ROTULOS[$chave]], $acoes),
        ];
    }

    /**
     * As ações cuja tela já existe, com o endereço. Ordem preservada: a primeira é a primária.
     *
     * @param  list<array{chave: string, rotulo: string}>  $acoes
     * @return list<array{chave: string, rotulo: string, href: string}>
     */
    public static function disponiveis(array $acoes): array
    {
        $saida = [];

        foreach ($acoes as $acao) {
            $rota = self::ROTAS[$acao['chave']];

            if (Route::has($rota)) {
                $saida[] = [...$acao, 'href' => route($rota)];
            }
        }

        return $saida;
    }
}
