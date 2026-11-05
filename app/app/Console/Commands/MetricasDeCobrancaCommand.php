<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Observabilidade\MetricasDeCobranca;
use App\Support\DataExibicao;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;

/**
 * STORY-086 CA-3 — imprime a conversão trial → pago em 30 dias, a conversão do checkout (por tipo, meio
 * e ciclo, Pix anual incluso) e o churn mensal. Só leitura; sem painel (fora de escopo da estória).
 */
final class MetricasDeCobrancaCommand extends Command
{
    /** @var string */
    protected $signature = 'cobranca:metricas
        {--dias=90 : Quantos dias para trás entram nas conversões}
        {--mes= : Mês do churn (AAAA-MM); padrão: o mês passado}';

    /** @var string */
    protected $description = 'Mostra conversão trial → pago, conversão do checkout e churn mensal (STORY-086 CA-3)';

    public function handle(MetricasDeCobranca $metricas): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $ate = CarbonImmutable::now();
        $de = $ate->subDays($dias);

        try {
            $mes = $this->option('mes') ? CarbonImmutable::createFromFormat('!Y-m', (string) $this->option('mes'), DataExibicao::fuso()) : $ate->setTimezone(DataExibicao::fuso())->subMonthNoOverflow();
        } catch (InvalidFormatException) {
            $mes = null;
        }

        if ($mes === null) {
            $this->error('Mês inválido: use AAAA-MM.');

            return self::INVALID;
        }

        $trial = $metricas->conversaoTrialParaPago($de, $ate);
        $this->line(sprintf('Trial → pago (30 dias), ativações dos últimos %d dias já maduras: %d de %d (%s)', $dias, $trial['convertidos'], $trial['coorte'], $this->percentual($trial['taxa'])));

        $checkout = $metricas->conversaoDoCheckout($de, $ate);
        $this->line(sprintf('Checkout, últimos %d dias: %d concluídos de %d iniciados (%s)', $dias, $checkout['concluidos'], $checkout['iniciados'], $this->percentual($checkout['taxa'])));

        foreach ($checkout['segmentos'] as $s) {
            $this->line(sprintf('  %s · %s · %s%s: %d de %d (%s)', $s['tipo'], $s['meio'], $s['ciclo'] ?? '-', $s['renovacao'] ? ' · renovação' : '', $s['concluidos'], $s['iniciados'], $this->percentual($s['taxa'])));
        }

        $churn = $metricas->churnMensal($mes);
        $this->line(sprintf('Churn de %s: %d cancelados de %d na base de abertura (%s)', $churn['mes'], $churn['cancelados'], $churn['base'], $this->percentual($churn['taxa'])));

        return self::SUCCESS;
    }

    private function percentual(?float $taxa): string
    {
        return $taxa === null ? 'sem base' : sprintf('%.1f%%', $taxa * 100);
    }
}
