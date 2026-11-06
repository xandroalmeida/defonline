<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Ponto único de formatação de data e hora para pessoas (IDR-025).
 *
 * O armazenamento e `app.timezone` são UTC; tudo que aparece em tela, PDF, planilha, e-mail ou
 * backoffice passa por aqui e sai no fuso `app.display_timezone` (America/Sao_Paulo). Depois das
 * 21h em Brasília o UTC já é o dia seguinte: formatar direto do atributo mostra a data errada.
 *
 * Para um dia sem hora (data de fundação, competência) use {@see self::dia()}: não há instante
 * para converter e mudar o fuso mudaria o dia.
 */
final class DataExibicao
{
    public static function fuso(): string
    {
        return (string) config('app.display_timezone');
    }

    /** Instante no fuso de exibição; aceita string de data/hora (interpretada em UTC). */
    public static function converter(DateTimeInterface|string $instante): CarbonImmutable
    {
        $d = $instante instanceof DateTimeInterface
            ? CarbonImmutable::instance($instante)
            : CarbonImmutable::parse($instante, 'UTC');

        return $d->setTimezone(self::fuso());
    }

    /**
     * Data/hora que o provedor de pagamento devolve. Uma data só com o dia (`2026-11-05`, o formato do Asaas) é
     * o dia do calendário de Brasília, nunca meia-noite UTC: lida assim ela cairia 3 h antes, no dia anterior.
     */
    public static function doProvedor(string $valor): CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1
            ? CarbonImmutable::createFromFormat('!Y-m-d', $valor, self::fuso())
            : CarbonImmutable::parse($valor);
    }

    /**
     * O último segundo do dia de `$instante` em Brasília: o acesso pago vale durante todo o dia do vencimento,
     * até a cobrança do ciclo seguinte, e as telas mostram o mesmo dia que esta regra usa.
     */
    public static function fimDoDia(DateTimeInterface $instante): CarbonImmutable
    {
        return CarbonImmutable::instance($instante)->setTimezone(self::fuso())->setTime(23, 59, 59);
    }

    public static function agora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::fuso());
    }

    /** O dia corrente em Brasília (meia-noite local). */
    public static function hoje(): CarbonImmutable
    {
        return self::agora()->startOfDay();
    }

    /**
     * O início do dia SEGUINTE a `$dia` (`Y-m-d`, um dia do calendário de Brasília), como instante em UTC: o limite
     * exclusivo de "até o fim do dia `$dia`" para quem escolhe uma data local (ex.: contas cadastradas até 31/10
     * entram até 31/10 23:59:59 em Brasília, e não até 21:00 do dia 31 nem até 03:00 do dia 1 em UTC).
     */
    public static function inicioDoDiaSeguinte(string $dia): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $dia, self::fuso())->addDay()->startOfDay()->utc();
    }

    public static function ano(): int
    {
        return self::agora()->year;
    }

    /** `dd/mm/aaaa` */
    public static function data(DateTimeInterface|string|null $instante, string $vazio = ''): string
    {
        return $instante === null ? $vazio : self::converter($instante)->format('d/m/Y');
    }

    /** `dd/mm/aaaa HH:mm` */
    public static function dataHora(DateTimeInterface|string|null $instante, string $vazio = ''): string
    {
        return $instante === null ? $vazio : self::converter($instante)->format('d/m/Y H:i');
    }

    /** `HH:mm` */
    public static function hora(DateTimeInterface|string|null $instante, string $vazio = ''): string
    {
        return $instante === null ? $vazio : self::converter($instante)->format('H:i');
    }

    /** `dd/mm/aaaa` de uma data sem hora: sem conversão de fuso. */
    public static function dia(?DateTimeInterface $dia, string $vazio = ''): string
    {
        return $dia === null ? $vazio : $dia->format('d/m/Y');
    }
}
