<?php

declare(strict_types=1);

use App\Support\DataExibicao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
|-------------------------------------------------------------------------
| IDR-025 — datas mostradas a pessoas saem em horário de Brasília; armazenamento e app.timezone
| ficam em UTC; a sessão do banco é UTC em qualquer ambiente.
|
| Relógio congelado às 22:30 de Brasília = 01:30 do dia seguinte em UTC: é a janela em que formatar
| direto do atributo mostrava o dia errado.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    Carbon::setTestNow('2026-11-06 01:30:00');
});

/** @return list<string> arquivo:linha de cada linha de app/ e resources/views que casa com o padrão. */
function _linhasQueCasam(string $padrao, string ...$ignorar): array
{
    $raiz = dirname(__DIR__, 3);
    $achados = [];

    foreach (['app', 'resources/views'] as $pasta) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$raiz}/{$pasta}", FilesystemIterator::SKIP_DOTS)) as $arquivo) {
            if (! str_ends_with($arquivo->getFilename(), '.php') || in_array($arquivo->getFilename(), $ignorar, true)) {
                continue;
            }
            foreach (file($arquivo->getPathname()) as $n => $linha) {
                if (preg_match($padrao, $linha) === 1) {
                    $achados[] = str_replace($raiz.'/', '', $arquivo->getPathname()).':'.($n + 1).' '.trim($linha);
                }
            }
        }
    }

    return $achados;
}

describe('DataExibicao', function () {
    it('o armazenamento segue em UTC e a exibição em America/Sao_Paulo', function () {
        expect(config('app.timezone'))->toBe('UTC')
            ->and(DataExibicao::fuso())->toBe('America/Sao_Paulo')
            ->and(now()->format('Y-m-d H:i'))->toBe('2026-11-06 01:30');
    });

    it('às 22:30 em Brasília ainda é o dia 05', function () {
        expect(DataExibicao::data(now()))->toBe('05/11/2026')
            ->and(DataExibicao::dataHora(now()))->toBe('05/11/2026 22:30')
            ->and(DataExibicao::hora(now()))->toBe('22:30')
            ->and(DataExibicao::agora()->format('d/m/Y H:i'))->toBe('05/11/2026 22:30')
            ->and(DataExibicao::hoje()->format('d/m/Y H:i'))->toBe('05/11/2026 00:00');
    });

    it('o ano do rodapé vira junto com Brasília, não com o UTC', function () {
        Carbon::setTestNow('2027-01-01 01:30:00');
        expect(DataExibicao::ano())->toBe(2026);

        Carbon::setTestNow('2027-01-01 03:00:00');
        expect(DataExibicao::ano())->toBe(2027);
    });

    it('aceita DateTimeImmutable, Carbon e texto (lido como UTC)', function () {
        expect(DataExibicao::data(new DateTimeImmutable('2026-11-06 01:30:00', new DateTimeZone('UTC'))))->toBe('05/11/2026')
            ->and(DataExibicao::dataHora('2026-11-06 01:30:00'))->toBe('05/11/2026 22:30')
            ->and(DataExibicao::dataHora(Carbon::parse('2026-11-06 01:30:00', 'UTC')->setTimezone('Asia/Tokyo')))->toBe('05/11/2026 22:30')
            ->and(DataExibicao::converter('2026-06-06 01:30:00')->format('H:i'))->toBe('22:30');
    });

    it('nulo vira o texto vazio indicado', function () {
        expect(DataExibicao::data(null))->toBe('')
            ->and(DataExibicao::dataHora(null, '—'))->toBe('—')
            ->and(DataExibicao::hora(null))->toBe('')
            ->and(DataExibicao::dia(null, '—'))->toBe('—');
    });

    it('um dia sem hora não muda de dia com o fuso', function () {
        expect(DataExibicao::dia(Carbon::parse('2026-11-06 00:00:00', 'UTC')))->toBe('06/11/2026');
    });

    it('o fuso é configurável', function () {
        config(['app.display_timezone' => 'Asia/Tokyo']);

        expect(DataExibicao::dataHora(now()))->toBe('06/11/2026 10:30');
    });
});

describe('sessão do banco', function () {
    it('é UTC: texto sem offset em coluna com fuso é lido como UTC', function () {
        $linha = DB::selectOne("select current_setting('TimeZone') as fuso, ('2026-11-06 01:30:00'::timestamptz)::text as instante");

        expect($linha->fuso)->toBe('UTC')
            ->and($linha->instante)->toBe('2026-11-06 01:30:00+00');
    });

    it('um instante gravado sem offset volta o mesmo, sem deslocar 3 horas', function () {
        DB::statement('create temporary table fuso_teste (id int, quando timestamptz, relogio timestamp)');
        DB::table('fuso_teste')->insert(['id' => 1, 'quando' => '2026-11-06 01:30:00', 'relogio' => '2026-11-06 01:30:00']);

        $linha = DB::table('fuso_teste')->first();

        expect(Carbon::parse($linha->quando)->utc()->format('Y-m-d H:i'))->toBe('2026-11-06 01:30')
            ->and($linha->relogio)->toBe('2026-11-06 01:30:00');
    });
});

describe('ponto único de formatação', function () {
    it('nenhuma view, componente ou serviço formata data para pessoas fora de DataExibicao', function () {
        $achados = _linhasQueCasam('/format\(\s*[\'"][^\'"]*(d\/m\/Y|H:i(?!:))/', 'DataExibicao.php');

        expect($achados)->toBe([], "Use App\\Support\\DataExibicao (IDR-025):\n".implode("\n", $achados));
    });

    it('nenhum código fixa o fuso de exibição nem usa o ano do UTC no rodapé', function () {
        $achados = _linhasQueCasam('/America\/Sao_Paulo|now\(\)->year/', 'DataExibicao.php');

        expect($achados)->toBe([], "Use DataExibicao::fuso() / ::ano() (IDR-025):\n".implode("\n", $achados));
    });
});
