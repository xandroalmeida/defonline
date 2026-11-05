<?php

declare(strict_types=1);

namespace App\Observabilidade;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * STORY-086 CA-3 — as contas do funil de contratação a partir de `evento_produto` (ADR-004: o PO consulta
 * o norte no mesmo banco do domínio). Só leitura. As definições estão em {@see EventosCobranca}; aqui
 * ficam as consultas, para o comando `cobranca:metricas` e qualquer painel futuro usarem a mesma conta.
 *
 * Instantes sempre com offset (`Y-m-d H:i:sP`): o banco local roda em America/Sao_Paulo e a aplicação em UTC (IDR-018).
 * Os meses contam em horário de Brasília.
 */
final class MetricasDeCobranca
{
    private const FUSO = 'America/Sao_Paulo';

    private const FORMATO = 'Y-m-d H:i:sP';

    /**
     * Conversão trial → pago (NRF §6.3): dos Usuários que ativaram o teste em `[$de, $ate)`, quantos
     * contrataram um PLANO (não conta compra de créditos, nem renovação) em até `$dias` depois da ativação.
     *
     * Só entra na conta quem já teve os `$dias` completos para converter — senão a taxa do mês corrente
     * aparece sempre baixa. Passe `$apenasMaduros = false` para ver a coorte inteira mesmo assim.
     *
     * @return array{coorte: int, convertidos: int, taxa: float|null}
     */
    public function conversaoTrialParaPago(CarbonInterface $de, CarbonInterface $ate, ?int $dias = null, bool $apenasMaduros = true): array
    {
        $dias ??= (int) config('cobranca.observabilidade.conversao_trial_dias', 30);

        if ($apenasMaduros) {
            $limite = CarbonImmutable::now()->subDays($dias);
            $ate = $ate->greaterThan($limite) ? $limite : $ate;
        }

        $linha = DB::selectOne(
            <<<'SQL'
            select count(*) as coorte,
                   count(*) filter (where exists (
                       select 1 from evento_produto c
                       where c.nome_evento = ?
                         and c.usuario_id = t.usuario_id
                         and c.propriedades->>'tipo' = 'assinatura'
                         and c.propriedades->>'renovacao' = 'false'
                         and c.ocorrido_em >= t.ocorrido_em
                         and c.ocorrido_em < t.ocorrido_em + make_interval(days => ?)
                   )) as convertidos
            from evento_produto t
            where t.nome_evento = ? and t.ocorrido_em >= ? and t.ocorrido_em < ?
            SQL,
            [EventosCobranca::CONTRATACAO_CONCLUIDA, $dias, EventosCobranca::TRIAL_ATIVADO, $de->format(self::FORMATO), $ate->format(self::FORMATO)],
        );

        $coorte = (int) $linha->coorte;
        $convertidos = (int) $linha->convertidos;

        return ['coorte' => $coorte, 'convertidos' => $convertidos, 'taxa' => $this->taxa($coorte, $convertidos)];
    }

    /**
     * Conversão do checkout: dos checkouts abertos em `[$de, $ate)`, quantos terminaram em contratação
     * concluída (ligados por `invoice_id`). O corte por tipo, meio, ciclo e renovação mostra Pix e cartão,
     * mensal e anual separados — o Pix anual entra como qualquer outro.
     *
     * @return array{iniciados: int, concluidos: int, taxa: float|null, segmentos: list<array{tipo: string, meio: string, ciclo: string|null, renovacao: bool, iniciados: int, concluidos: int, taxa: float|null}>}
     */
    public function conversaoDoCheckout(CarbonInterface $de, CarbonInterface $ate): array
    {
        $linhas = DB::select(
            <<<'SQL'
            select i.propriedades->>'tipo' as tipo,
                   i.propriedades->>'meio' as meio,
                   i.propriedades->>'ciclo' as ciclo,
                   (i.propriedades->>'renovacao')::boolean as renovacao,
                   count(*) as iniciados,
                   count(*) filter (where exists (
                       select 1 from evento_produto c
                       where c.nome_evento = ? and c.propriedades->>'invoice_id' = i.propriedades->>'invoice_id'
                   )) as concluidos
            from evento_produto i
            where i.nome_evento = ? and i.ocorrido_em >= ? and i.ocorrido_em < ?
            group by 1, 2, 3, 4
            order by 1, 2, 3, 4
            SQL,
            [EventosCobranca::CONTRATACAO_CONCLUIDA, EventosCobranca::CHECKOUT_INICIADO, $de->format(self::FORMATO), $ate->format(self::FORMATO)],
        );

        $iniciados = 0;
        $concluidos = 0;
        $segmentos = [];

        foreach ($linhas as $l) {
            $iniciados += (int) $l->iniciados;
            $concluidos += (int) $l->concluidos;
            $segmentos[] = [
                'tipo' => (string) $l->tipo,
                'meio' => (string) $l->meio,
                'ciclo' => $l->ciclo === null ? null : (string) $l->ciclo,
                'renovacao' => (bool) $l->renovacao,
                'iniciados' => (int) $l->iniciados,
                'concluidos' => (int) $l->concluidos,
                'taxa' => $this->taxa((int) $l->iniciados, (int) $l->concluidos),
            ];
        }

        return ['iniciados' => $iniciados, 'concluidos' => $concluidos, 'taxa' => $this->taxa($iniciados, $concluidos), 'segmentos' => $segmentos];
    }

    /**
     * Churn mensal (NRF §6.3) de `$mes` (qualquer instante dele; conta em Brasília): dos assinantes que
     * abriram o mês pagando, quantos deixaram de renovar até o fim dele. Quem assinou e cancelou dentro do
     * próprio mês não está na base de abertura e não entra (é abandono do primeiro ciclo, não perda de base).
     * Cancelamento revertido por reativação não conta; a base é líquida dos cancelamentos anteriores.
     *
     * @return array{mes: string, base: int, cancelados: int, taxa: float|null}
     */
    public function churnMensal(CarbonInterface $mes): array
    {
        $inicio = CarbonImmutable::instance($mes)->setTimezone(self::FUSO)->startOfMonth();
        $fim = $inicio->addMonth();

        $contratacoes = DB::table('evento_produto')
            ->where('nome_evento', EventosCobranca::CONTRATACAO_CONCLUIDA)
            ->whereRaw("propriedades->>'tipo' = 'assinatura'")
            ->whereRaw("propriedades->>'renovacao' = 'false'")
            ->whereRaw("propriedades->>'assinatura_id' is not null")
            ->where('ocorrido_em', '<', $inicio->format(self::FORMATO))
            ->selectRaw("propriedades->>'assinatura_id' as assinatura_id")
            ->distinct()
            ->pluck('assinatura_id')
            ->all();

        if ($contratacoes === []) {
            return ['mes' => $inicio->format('Y-m'), 'base' => 0, 'cancelados' => 0, 'taxa' => null];
        }

        $eventos = DB::table('evento_produto')
            ->whereIn('nome_evento', [EventosCobranca::CANCELAMENTO, EventosCobranca::CANCELAMENTO_REVERTIDO])
            ->where('ocorrido_em', '<', $fim->format(self::FORMATO))
            ->selectRaw("propriedades->>'assinatura_id' as assinatura_id, nome_evento, ocorrido_em")
            ->orderBy('ocorrido_em')
            ->orderBy('id')
            ->get();

        // Último estado de cada assinatura na abertura e no fechamento do mês: cancelada ou não.
        $naAbertura = [];
        $noFechamento = [];

        foreach ($eventos as $e) {
            $cancelada = $e->nome_evento === EventosCobranca::CANCELAMENTO;
            $noFechamento[$e->assinatura_id] = $cancelada;

            if (CarbonImmutable::parse($e->ocorrido_em)->lessThan($inicio)) {
                $naAbertura[$e->assinatura_id] = $cancelada;
            }
        }

        $base = array_filter($contratacoes, fn (string $id): bool => ! ($naAbertura[$id] ?? false));
        $cancelados = count(array_filter($base, fn (string $id): bool => $noFechamento[$id] ?? false));

        return [
            'mes' => $inicio->format('Y-m'),
            'base' => count($base),
            'cancelados' => $cancelados,
            'taxa' => $this->taxa(count($base), $cancelados),
        ];
    }

    /**
     * Pagamentos recusados × confirmados desde `$desde` (CA-4). O alerta usa a janela de 1 hora.
     *
     * @return array{confirmados: int, recusados: int, total: int, taxa: float|null}
     */
    public function falhaDePagamento(CarbonInterface $desde): array
    {
        $linhas = DB::table('evento_produto')
            ->whereIn('nome_evento', [EventosCobranca::PAGAMENTO_CONFIRMADO, EventosCobranca::PAGAMENTO_RECUSADO])
            ->where('ocorrido_em', '>=', $desde->format(self::FORMATO))
            ->selectRaw('nome_evento, count(*) as total')
            ->groupBy('nome_evento')
            ->pluck('total', 'nome_evento');

        $confirmados = (int) ($linhas[EventosCobranca::PAGAMENTO_CONFIRMADO] ?? 0);
        $recusados = (int) ($linhas[EventosCobranca::PAGAMENTO_RECUSADO] ?? 0);
        $total = $confirmados + $recusados;

        return ['confirmados' => $confirmados, 'recusados' => $recusados, 'total' => $total, 'taxa' => $this->taxa($total, $recusados)];
    }

    private function taxa(int $total, int $parte): ?float
    {
        return $total === 0 ? null : $parte / $total * 1.0;
    }
}
