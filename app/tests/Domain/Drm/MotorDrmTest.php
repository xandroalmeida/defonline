<?php

declare(strict_types=1);

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\MotorDrm;
use App\Domain\Drm\Percentual;
use App\Domain\Drm\Setor;
use Carbon\CarbonImmutable;

require_once __DIR__.'/MotorDrmHelpers.php';

/*
|-------------------------------------------------------------------------
| STORY-048 — fórmulas, ordem, rótulos, `tipo`/`destaque`, nominal e percentual.
| Referência: design/estrutura-drm.md v1.1 §1. Os valores esperados aqui foram
| calculados à mão (comentários), não pelo motor.
|-------------------------------------------------------------------------
*/

describe('CA-1 — estrutura exata do modo "fora"', function () {
    it('produz as linhas na ordem do contrato, com nível, tipo, destaque e nominal', function () {
        $d = motorDrm_calcular(motorDrm_comercioCompleto());

        $esperado = [
            // código, nível, tipo, destaque, nominal
            ['L1', 1, 'item', true, '100000.00'],
            ['L2', 1, 'deducao', false, '6000.00'],
            ['L3', 1, 'total', false, '94000.00'],
            ['L4', 1, 'deducao', false, '36000.00'],
            ['L5', 1, 'total', false, '58000.00'],
            ['L6', 1, 'deducao', false, '22800.75'],
            ['L6a', 2, 'subtotal', false, '4000.50'],
            ['L6b', 2, 'subtotal', false, '18800.25'],
            ['L7', 1, 'total', false, '35199.25'],
            ['L7b', 1, 'deducao', false, '150.00'],
            ['L8', 1, 'deducao', false, '1000.00'],
            ['L8a', 2, 'subtotal', false, '900.00'],
            ['L8b', 2, 'subtotal', false, '100.00'],
            ['L9', 1, 'total', true, '34349.25'],
            ['L10', 1, 'deducao', false, '8000.00'],
            ['L11', 1, 'total', true, '26349.25'],
        ];

        $obtido = array_map(
            fn (array $l) => [$l['codigo'], $l['nivel'], $l['tipo'], $l['destaque'], $l['nominal']],
            $d['linhas']['fora'],
        );

        expect($obtido)->toBe($esperado);
    });

    it('rotula cada linha com o pacote validado, e o custo pelo setor (CPV/CMV/CSP)', function (Setor $setor, string $rotuloCusto) {
        $d = motorDrm_calcular(motorDrm_comercioCompleto(), $setor);
        $rotulos = array_column($d['linhas']['fora'], 'rotulo', 'codigo');

        expect($rotulos)->toBe([
            'L1' => 'Receita Bruta',
            'L2' => 'Deduções da Receita Bruta',
            'L3' => 'Receita Operacional Líquida',
            'L4' => $rotuloCusto,
            'L5' => 'Lucro Bruto',
            'L6' => 'Despesas Operacionais',
            'L6a' => 'Despesas com Vendas',
            'L6b' => 'Despesas Administrativas e Gerais',
            'L7' => 'Resultado Operacional',
            'L7b' => 'Outras receitas e despesas',
            'L8' => 'Despesas Financeiras',
            'L8a' => 'Juros pagos',
            'L8b' => 'Tarifas bancárias',
            'L9' => 'Resultado antes do pró-labore',
            'L10' => 'Pró-labore',
            'L11' => 'Lucro Líquido do Mês',
        ]);
    })->with([
        'indústria' => [Setor::Industria, 'Custo dos Produtos Vendidos (CPV)'],
        'comércio' => [Setor::Comercio, 'Custo das Mercadorias Vendidas (CMV)'],
        'serviços' => [Setor::Servicos, 'Custo dos Serviços Prestados (CSP)'],
    ]);

    it('neste modo L7 não se chama EBITDA (§3.1)', function () {
        $d = motorDrm_calcular(motorDrm_comercioCompleto());

        expect(motorDrm_linha($d, 'fora', 'L7')['rotulo'])->not->toContain('EBITDA');
    });
});

describe('CA-1b — estrutura exata do modo "dentro"', function () {
    it('tem L6c, EBITDA em L7 e colapsa L9/L10 no Lucro Líquido', function () {
        $d = motorDrm_calcular(motorDrm_comercioCompleto());

        $obtido = array_map(
            fn (array $l) => [$l['codigo'], $l['nivel'], $l['tipo'], $l['destaque'], $l['rotulo'], $l['nominal']],
            $d['linhas']['dentro'],
        );

        expect($obtido)->toBe([
            ['L1', 1, 'item', true, 'Receita Bruta', '100000.00'],
            ['L2', 1, 'deducao', false, 'Deduções da Receita Bruta', '6000.00'],
            ['L3', 1, 'total', false, 'Receita Operacional Líquida', '94000.00'],
            ['L4', 1, 'deducao', false, 'Custo das Mercadorias Vendidas (CMV)', '36000.00'],
            ['L5', 1, 'total', false, 'Lucro Bruto', '58000.00'],
            ['L6', 1, 'deducao', false, 'Despesas Operacionais', '30800.75'],
            ['L6a', 2, 'subtotal', false, 'Despesas com Vendas', '4000.50'],
            ['L6b', 2, 'subtotal', false, 'Despesas Administrativas e Gerais', '26800.25'],
            ['L6c', 3, 'item', false, 'Pró-labore', '8000.00'],
            ['L7', 1, 'total', true, 'Resultado Operacional (EBITDA)', '27199.25'],
            ['L7b', 1, 'deducao', false, 'Outras receitas e despesas', '150.00'],
            ['L8', 1, 'deducao', false, 'Despesas Financeiras', '1000.00'],
            ['L8a', 2, 'subtotal', false, 'Juros pagos', '900.00'],
            ['L8b', 2, 'subtotal', false, 'Tarifas bancárias', '100.00'],
            ['L11', 1, 'total', true, 'Lucro Líquido do Mês', '26349.25'],
        ]);
    });

    it('não tem L9 nem L10', function () {
        $d = motorDrm_calcular(motorDrm_comercioCompleto());

        expect(motorDrm_codigos($d, 'dentro'))->not->toContain('L9')->not->toContain('L10');
    });

    it('L6c (nível 3) só existe com as despesas administrativas em modo detalhado', function () {
        $agregado = motorDrm_payload(
            ['D01' => '1000.00', 'D02' => '0.00', 'D15' => '100.00', 'D40' => '50.00'],
            [Grupo::Administrativas->value => Modo::Agregado],
        );
        $d = motorDrm_calcular($agregado);

        expect(motorDrm_codigos($d, 'dentro'))->not->toContain('L6c')
            // O pró-labore continua dentro de L6b: 100,00 + 50,00.
            ->and(motorDrm_linha($d, 'dentro', 'L6b')['nominal'])->toBe('150.00')
            ->and(motorDrm_codigos($d, 'fora'))->toContain('L10');
    });

    it('o modo é de apresentação: os dois arrays saem do mesmo cálculo, sem parâmetro de modo', function () {
        $d = motorDrm_calcular(motorDrm_comercioCompleto());

        expect(array_keys($d['linhas']))->toBe(['fora', 'dentro']);
    });
});

describe('CA-1d — outras receitas e despesas (L7b)', function () {
    it('fica após o Resultado Operacional nos dois modos e carrega D41 − D42', function () {
        $d = motorDrm_calcular(motorDrm_comercioCompleto());

        foreach (['fora', 'dentro'] as $modo) {
            $codigos = motorDrm_codigos($d, $modo);
            expect($codigos[array_search('L7', $codigos, true) + 1])->toBe('L7b')
                ->and(motorDrm_linha($d, $modo, 'L7b')['nominal'])->toBe('150.00');
        }
    });

    it('pode ser negativa', function () {
        $d = motorDrm_calcular(motorDrm_payload(['D01' => '1000.00', 'D02' => '0.00', 'D40' => '0.00', 'D41' => '10.00', 'D42' => '35.50']));

        expect(motorDrm_linha($d, 'fora', 'L7b')['nominal'])->toBe('-25.50')
            ->and(motorDrm_linha($d, 'fora', 'L7b')['percentual'])->toBe('-2.5500');
    });

    it('é omitida quando D41 e D42 são ambos zero (ou ausentes), nos dois modos', function (?string $d41, ?string $d42) {
        $d = motorDrm_calcular(motorDrm_payload(['D01' => '1000.00', 'D02' => '0.00', 'D40' => '0.00', 'D41' => $d41, 'D42' => $d42]));

        expect(motorDrm_codigos($d, 'fora'))->not->toContain('L7b')
            ->and(motorDrm_codigos($d, 'dentro'))->not->toContain('L7b');
    })->with([
        'ambos 0,00' => ['0.00', '0.00'],
        'ambos ausentes' => [null, null],
        'um ausente, outro 0,00' => [null, '0.00'],
    ]);

    it('aparece, zerada, quando os dois campos têm valor e se anulam (a linha existe se houve lançamento)', function () {
        $d = motorDrm_calcular(motorDrm_payload(['D01' => '1000.00', 'D02' => '0.00', 'D40' => '0.00', 'D41' => '80.00', 'D42' => '80.00']));

        expect(motorDrm_linha($d, 'fora', 'L7b')['nominal'])->toBe('0.00');
    });

    it('só D41 ou só D42 já bastam para a linha existir', function (array $campos, string $nominal) {
        $d = motorDrm_calcular(motorDrm_payload(['D01' => '1000.00', 'D02' => '0.00', 'D40' => '0.00', ...$campos]));

        expect(motorDrm_linha($d, 'fora', 'L7b')['nominal'])->toBe($nominal);
    })->with([
        'só receita' => [['D41' => '5.00'], '5.00'],
        'só despesa' => [['D42' => '5.00'], '-5.00'],
    ]);
});

describe('CA-2 — nominal e percentual sobre a Receita Bruta', function () {
    it('cada linha carrega os dois; L1 é a âncora de 100%', function () {
        $d = motorDrm_calcular(motorDrm_comercioCompleto());

        expect(motorDrm_linha($d, 'fora', 'L1')['percentual'])->toBe('100.0000');
        foreach (['fora', 'dentro'] as $modo) {
            foreach ($d['linhas'][$modo] as $linha) {
                expect($linha['nominal'])->toBeString()->and($linha['percentual'])->toBeString();
            }
        }
    });

    it('percentual = nominal / receita × 100, truncado em quatro casas (contas à mão)', function () {
        $d = motorDrm_calcular(motorDrm_comercioCompleto());

        $esperado = [
            'L2' => '6.0000',
            'L3' => '94.0000',
            'L5' => '58.0000',
            'L6' => '22.8007', // 22,80075
            'L6a' => '4.0005',
            'L6b' => '18.8002', // 18,80025
            'L7' => '35.1992', // 35,19925
            'L7b' => '0.1500',
            'L8' => '1.0000',
            'L9' => '34.3492', // 34,34925
            'L10' => '8.0000',
            'L11' => '26.3492', // 26,34925
        ];
        foreach ($esperado as $codigo => $percentual) {
            expect(motorDrm_linha($d, 'fora', $codigo)['percentual'])->toBe($percentual, $codigo);
        }
        expect(motorDrm_linha($d, 'dentro', 'L6')['percentual'])->toBe('30.8007')
            ->and(motorDrm_linha($d, 'dentro', 'L7')['percentual'])->toBe('27.1992');
    });

    it('prejuízo: nominal e percentual negativos, com sinal', function () {
        $d = motorDrm_calcular(motorDrm_payload(['D01' => '1000.00', 'D02' => '100.00', 'D04' => '1500.00', 'D40' => '0.00']));

        expect(motorDrm_linha($d, 'fora', 'L5')['nominal'])->toBe('-600.00')
            ->and(motorDrm_linha($d, 'fora', 'L5')['percentual'])->toBe('-60.0000')
            ->and(motorDrm_linha($d, 'fora', 'L11')['nominal'])->toBe('-600.00');
    });

    it('os nominais são strings de duas casas, sem float em lugar nenhum da saída', function () {
        $d = motorDrm_calcular(motorDrm_comercioCompleto());

        array_walk_recursive($d, function ($valor) {
            expect(is_float($valor))->toBeFalse();
        });
        foreach ($d['linhas']['fora'] as $linha) {
            expect($linha['nominal'])->toMatch('/^-?\d+\.\d{2}$/')->and($linha['percentual'])->toMatch('/^-?\d+\.\d{4}$/');
        }
    });

    it('a apresentação (uma casa) sai do valor exato, sem arredondar duas vezes', function () {
        // 13,34996…% de 100000,00: arredondar antes para 4 casas daria 13,3500 e depois 13,4.
        $d = motorDrm_calcular(motorDrm_payload(['D01' => '1000000.00', 'D02' => '133499.99', 'D40' => '0.00']));

        expect(motorDrm_linha($d, 'fora', 'L2')['percentual'])->toBe('13.3499')
            ->and(Percentual::formatar(motorDrm_linha($d, 'fora', 'L2')['percentual']))->toBe('13,3');
    });
});

describe('CA-3 — três setores, mesma estrutura', function () {
    it('as linhas e a ordem são idênticas nos três setores', function () {
        $base = motorDrm_comercioCompleto();
        $porSetor = [];
        foreach (Setor::cases() as $setor) {
            $d = motorDrm_calcular($base, $setor);
            $porSetor[$setor->value] = [motorDrm_codigos($d, 'fora'), motorDrm_codigos($d, 'dentro')];
        }

        expect($porSetor['comercio'])->toBe($porSetor['industria'])->and($porSetor['servicos'])->toBe($porSetor['industria']);
    });

    it('campo suprimido pelo setor é ignorado, não somado, e não muda a estrutura', function (Setor $setor, string $campo, string $custo) {
        // O payload traz valor onde o setor não coleta (dado fora do contrato): a soma do custo o ignora.
        $payload = motorDrm_payload(['D01' => '1000.00', 'D02' => '0.00', 'D06' => '10.00', 'D40' => '0.00', $campo => '777.00']);
        $d = motorDrm_calcular($payload, $setor);

        expect(motorDrm_linha($d, 'fora', 'L4')['nominal'])->toBe($custo);
    })->with([
        'serviços ignora D04' => [Setor::Servicos, 'D04', '10.00'],
        'serviços ignora D05' => [Setor::Servicos, 'D05', '10.00'],
        'comércio ignora D05' => [Setor::Comercio, 'D05', '10.00'],
        'comércio soma D04' => [Setor::Comercio, 'D04', '787.00'],
        'indústria soma D05' => [Setor::Industria, 'D05', '787.00'],
    ]);

    it('ausente (nulo) e 0,00 produzem os mesmos valores', function () {
        $ausente = motorDrm_calcular(motorDrm_payload(['D01' => '1000.00', 'D02' => '0.00', 'D40' => '0.00']), Setor::Servicos);
        $zero = motorDrm_calcular(motorDrm_payload(['D01' => '1000.00', 'D02' => '0.00', 'D04' => '0.00', 'D05' => '0.00', 'D40' => '0.00']), Setor::Servicos);

        expect($ausente['linhas'])->toBe($zero['linhas']);
    });
});

describe('CA-4 — modo agregado e detalhado', function () {
    it('agregado usa o total informado e ignora os itens; detalhado soma os itens e ignora o total', function () {
        $campos = ['D01' => '1000.00', 'D02' => '0.00', 'D40' => '0.00', 'D03' => '300.00', 'D04' => '10.00', 'D06' => '20.00'];

        $agregado = motorDrm_calcular(motorDrm_payload($campos, [Grupo::Custo->value => Modo::Agregado]));
        $detalhado = motorDrm_calcular(motorDrm_payload($campos, [Grupo::Custo->value => Modo::Detalhado]));

        expect(motorDrm_linha($agregado, 'fora', 'L4')['nominal'])->toBe('300.00')
            ->and(motorDrm_linha($detalhado, 'fora', 'L4')['nominal'])->toBe('30.00');
    });

    it('os quatro grupos funcionam nos dois modos e a estrutura de saída é a mesma', function () {
        $itens = ['D04' => '10.00', 'D06' => '5.00', 'D09' => '7.00', 'D10' => '3.00', 'D16' => '100.00', 'D23' => '50.00', 'D38' => '4.00', 'D39' => '1.00'];
        $totais = ['D03' => '15.00', 'D08' => '10.00', 'D15' => '150.00', 'D37' => '5.00'];
        $base = ['D01' => '1000.00', 'D02' => '0.00', 'D40' => '20.00'];

        $detalhado = motorDrm_calcular(motorDrm_payload([...$base, ...$itens]));
        $agregado = motorDrm_calcular(motorDrm_payload([...$base, ...$totais], array_fill_keys(array_map(fn ($g) => $g->value, Grupo::cases()), Modo::Agregado)));

        foreach (['fora', 'dentro'] as $modo) {
            // Níveis 1 e 2 idênticos; o nível 3 (L6c) é "exibido só quando o grupo está detalhado" (§1.2).
            $semNivel3 = fn (array $d): array => array_values(array_filter($d['linhas'][$modo], fn (array $l) => $l['nivel'] < 3));
            expect(array_column($semNivel3($agregado), 'codigo'))->toBe(array_column($semNivel3($detalhado), 'codigo'));
            foreach ($semNivel3($detalhado) as $i => $linha) {
                $outra = $semNivel3($agregado)[$i];
                // Mesmos valores em tudo, salvo nas linhas de item financeiro, que o usuário não informou.
                if (in_array($linha['codigo'], ['L8a', 'L8b'], true)) {
                    continue;
                }
                expect($outra['nominal'])->toBe($linha['nominal'], $linha['codigo'])
                    ->and($outra['percentual'])->toBe($linha['percentual'], $linha['codigo']);
            }
        }
    });

    it('L8a/L8b com despesas financeiras agregadas: sem nominal, motivo grupo_agregado, nunca inferido do total', function () {
        $d = motorDrm_calcular(motorDrm_payload(
            ['D01' => '1000.00', 'D02' => '0.00', 'D40' => '0.00', 'D37' => '5.00'],
            [Grupo::Financeiras->value => Modo::Agregado],
        ));

        foreach (['L8a', 'L8b'] as $codigo) {
            $linha = motorDrm_linha($d, 'fora', $codigo);
            expect($linha['nominal'])->toBeNull()
                ->and($linha['percentual'])->toBeNull()
                ->and($linha['motivo_nominal'])->toBe('indisponivel:grupo_agregado')
                ->and($linha['motivo'])->toBe('indisponivel:grupo_agregado')
                ->and($linha['mensagem_nominal'])->toBe('Este grupo foi lançado em modo agregado — os itens não foram informados separadamente.');
        }
        expect(motorDrm_linha($d, 'fora', 'L8')['nominal'])->toBe('5.00');
    });
});

describe('CA-5 — sem faturamento', function () {
    function motorDrm_semFaturamento(): array
    {
        return motorDrm_calcular(motorDrm_payload(['D01' => '0.00', 'D02' => '0.00', 'D04' => '100.00', 'D16' => '300.00', 'D38' => '20.00', 'D40' => '50.00']));
    }

    it('todos os percentuais ficam indisponíveis, com motivo e mensagem semânticos', function () {
        $d = motorDrm_semFaturamento();

        foreach (['fora', 'dentro'] as $modo) {
            foreach ($d['linhas'][$modo] as $linha) {
                expect($linha['percentual'])->toBeNull($linha['codigo'])
                    ->and($linha['motivo'])->toBe('indisponivel:sem_faturamento')
                    ->and($linha['mensagem'])->toBe('Sem faturamento neste mês — os percentuais sobre a receita não se aplicam.');
            }
        }
    });

    it('os valores nominais continuam presentes (quanto custou manter o negócio parado)', function () {
        $d = motorDrm_semFaturamento();

        // L5 = 0 − 100 = −100; L7 = −100 − 300 = −400; L9 = −400 − 20 = −420; L11 = −420 − 50 = −470.
        expect(motorDrm_linha($d, 'fora', 'L1')['nominal'])->toBe('0.00')
            ->and(motorDrm_linha($d, 'fora', 'L5')['nominal'])->toBe('-100.00')
            ->and(motorDrm_linha($d, 'fora', 'L7')['nominal'])->toBe('-400.00')
            ->and(motorDrm_linha($d, 'fora', 'L9')['nominal'])->toBe('-420.00')
            ->and(motorDrm_linha($d, 'fora', 'L11')['nominal'])->toBe('-470.00')
            ->and(motorDrm_linha($d, 'dentro', 'L11')['nominal'])->toBe('-470.00');
    });

    it('não lança exceção nem produz zero disfarçado de percentual', function () {
        expect(fn () => motorDrm_semFaturamento())->not->toThrow(Throwable::class);

        foreach (motorDrm_semFaturamento()['linhas']['fora'] as $linha) {
            expect($linha['percentual'])->not->toBe('0.0000')->and($linha['percentual'])->not->toBe(0);
        }
    });

    it('com faturamento, motivo e mensagem são nulos nas linhas disponíveis', function () {
        $linha = motorDrm_linha(motorDrm_calcular(motorDrm_comercioCompleto()), 'fora', 'L1');

        expect($linha['motivo'])->toBeNull()->and($linha['mensagem'])->toBeNull()
            ->and($linha['motivo_nominal'])->toBeNull()->and($linha['mensagem_nominal'])->toBeNull();
    });

    it('sem faturamento e sem despesa nenhuma: tudo zero, percentuais indisponíveis', function () {
        $d = motorDrm_calcular(motorDrm_payload(['D01' => '0.00', 'D02' => '0.00', 'D40' => '0.00']));

        expect(motorDrm_linha($d, 'fora', 'L11')['nominal'])->toBe('0.00')
            ->and(motorDrm_linha($d, 'fora', 'L11')['percentual'])->toBeNull();
    });
});

describe('CA-6 — EBITDA padrão', function () {
    it('L7 do modo "dentro" é L5 − (L6 + pró-labore) do modo "fora"', function () {
        $d = motorDrm_calcular(motorDrm_comercioCompleto());

        $l5 = Dinheiro::deDecimal(motorDrm_linha($d, 'fora', 'L5')['nominal']);
        $l6 = Dinheiro::deDecimal(motorDrm_linha($d, 'fora', 'L6')['nominal']);
        $prolabore = Dinheiro::deDecimal(motorDrm_linha($d, 'fora', 'L10')['nominal']);

        expect(motorDrm_linha($d, 'dentro', 'L7')['nominal'])->toBe(Dinheiro::decimal($l5 - ($l6 + $prolabore)))
            ->and(motorDrm_linha($d, 'dentro', 'L7')['rotulo'])->toBe('Resultado Operacional (EBITDA)');
    });
});

describe('carimbo e cabeçalho do snapshot', function () {
    it('estampa versão do motor, competência, setor, carimbo recebido por parâmetro e modos', function () {
        $d = motorDrm_calcular(motorDrm_payload(['D01' => '1.00', 'D02' => '0.00', 'D40' => '0.00'], [Grupo::Custo->value => Modo::Agregado]), Setor::Servicos);

        expect(array_slice($d, 0, 5, true))->toBe([
            'motor_drm_version' => '1.0.0',
            'competencia' => '2026-09',
            'setor' => 'servicos',
            'gerado_em' => '2026-10-02T12:00:00+00:00',
            'modos' => ['custo' => 'agregado', 'vendas' => 'detalhado', 'administrativas' => 'detalhado', 'financeiras' => 'detalhado'],
        ]);
    });
});

it('o carimbo sai sempre em UTC, qualquer que seja o fuso recebido', function () {
    $calcular = fn (string $instante) => (new MotorDrm)->calcular(motorDrm_comercioCompleto(), Setor::Comercio, Competencia::deTexto('2026-09'), new DateTimeImmutable($instante));

    expect($calcular('2026-10-02T09:00:00-03:00')['gerado_em'])->toBe('2026-10-02T12:00:00+00:00')
        ->and($calcular('2026-10-02T12:00:00+00:00'))->toBe($calcular('2026-10-02T09:00:00-03:00'));
});

describe('contrato de entrada', function () {
    it('recusa campo malformado ou negativo, sem adivinhar', function (mixed $valor) {
        $payload = motorDrm_comercioCompleto();
        $payload['D01'] = $valor;

        expect(fn () => motorDrm_calcular($payload))->toThrow(InvalidArgumentException::class, 'D01');
    })->with([
        'texto' => ['abc'],
        'uma casa' => ['10.5'],
        'três casas' => ['10.555'],
        'vírgula' => ['10,50'],
        'negativo' => ['-1.00'],
        'float' => [10.5],
        'inteiro' => [10],
    ]);

    it('recusa payload sem modos ou com modo inválido', function (mixed $modos) {
        $payload = motorDrm_comercioCompleto();
        $payload['modos'] = $modos;

        expect(fn () => motorDrm_calcular($payload))->toThrow(InvalidArgumentException::class);
    })->with([
        'ausente' => [null],
        'texto' => ['detalhado'],
        'grupo faltando' => [['custo' => 'agregado']],
        'valor inválido' => [['custo' => 'x', 'vendas' => 'agregado', 'administrativas' => 'agregado', 'financeiras' => 'agregado']],
        'valor não texto' => [['custo' => 1, 'vendas' => 'agregado', 'administrativas' => 'agregado', 'financeiras' => 'agregado']],
    ]);

    it('rótulo ou mensagem ausentes no pacote de conteúdo são erro, não texto vazio gravado para sempre', function () {
        config(['drm.rotulos.linhas.fora.L9' => null]);
        expect(fn () => motorDrm_calcular(motorDrm_comercioCompleto()))->toThrow(LogicException::class, 'L9');

        config(['drm.rotulos.linhas.fora.L9' => 'Resultado antes do pró-labore', 'drm.rotulos.indisponibilidade.indisponivel:sem_faturamento' => null]);
        expect(fn () => motorDrm_semFaturamento())->toThrow(LogicException::class, 'sem_faturamento');
    });

    it('valores no teto do campo (999.999.999,99) somam sem estouro', function () {
        $d = motorDrm_calcular(motorDrm_payload(['D01' => '999999999.99', 'D02' => '0.00', 'D04' => '999999999.99', 'D40' => '999999999.99']));

        expect(motorDrm_linha($d, 'fora', 'L11')['nominal'])->toBe('-999999999.99')
            ->and(motorDrm_linha($d, 'fora', 'L11')['percentual'])->toBe('-100.0000');
    });
});

describe('integração com o que a STORY-047 já calcula', function () {
    it('o Lucro Líquido do motor concorda com o do lançamento (usado no alerta A3)', function () {
        $texto = ['D01' => '84.300,00', 'D02' => '5.058,00', 'D04' => '41.200,00', 'D09' => '6.100,00', 'D16' => '20.702,00', 'D38' => '940,00', 'D39' => '240,00', 'D40' => '7.000,00', 'D41' => '300,00', 'D42' => '50,00'];
        $lancamento = Lancamento::deTexto(Setor::Comercio, array_fill_keys(array_map(fn ($g) => $g->value, Grupo::cases()), Modo::Detalhado), $texto);

        $d = (new MotorDrm)->calcular($lancamento->payload(), Setor::Comercio, Competencia::deData(CarbonImmutable::parse('2026-08-01')), new DateTimeImmutable('2026-09-01T00:00:00+00:00'));

        expect(Dinheiro::deDecimal(motorDrm_linha($d, 'fora', 'L11')['nominal']))->toBe($lancamento->lucroLiquido());
    });
});
