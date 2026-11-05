<?php

declare(strict_types=1);

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Faixas;
use App\Domain\Drm\Leitura\Leitura;
use App\Domain\Drm\Leitura\LeituraDrm;
use App\Domain\Drm\Serie\MesDaSerie;
use App\Domain\Drm\Serie\SerieDrm;
use App\Domain\Drm\Setor;

/*
|-------------------------------------------------------------------------
| STORY-054 — faróis e leitura em linguagem simples.
|
| Tudo puro: o mês entra como `MesDaSerie` (o que o snapshot guarda) e a série como `SerieDrm`.
| Cada teste fixa a conta no comentário. A leitura é conferida frase a frase, porque o contrato
| é que ela seja determinística (CA-3) e que nunca invente texto (CA-7).
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    config()->set('drm.lacunas_path', '');
});

/**
 * Mês padrão (R$ 10.000,00 vendidos, resultado R$ 2.900,00): L2 1.000 · L4 3.000 · L6 2.000 · L8 100 · L10 1.000.
 *
 * @param  array<string, int|null>  $nominais
 * @param  array<string, string|null>|null  $percentuais  `null` = os de cima; `[]` = nenhum (mês sem faturamento)
 * @param  list<string>|null  $nivel1
 */
function leitura_mes(string $competencia = '2026-10', array $nominais = [], ?array $percentuais = null, ?array $nivel1 = null): MesDaSerie
{
    $nominais = [
        'L1' => 1_000_000, 'L2' => 100_000, 'L4' => 300_000, 'L6' => 200_000, 'L8' => 10_000, 'L10' => 100_000,
        'L11' => 290_000, ...$nominais,
    ];

    return new MesDaSerie(
        Competencia::deTexto($competencia),
        1,
        $nominais,
        $percentuais ?? ['L1' => '100.0000', 'L4' => '30.0000', 'L5' => '60.0000', 'L6' => '20.0000', 'L6a' => '5.0000', 'L6b' => '15.0000',
            'L7' => '40.0000', 'L8' => '1.0000', 'L10' => '10.0000', 'L11' => '29.0000'],
        [],
        $nivel1 ?? ['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8', 'L9', 'L10', 'L11'],
    );
}

/** @param  list<MesDaSerie>  $meses */
function leitura_gerar(MesDaSerie $mes, array $meses = [], Setor $setor = Setor::Comercio, string $posicao = 'fora'): ?Leitura
{
    return (new LeituraDrm)->gerar($mes, new SerieDrm([...$meses, $mes]), $setor, $posicao);
}

/** Calibra uma linha: verde a partir de 50, amarelo de 10 a 50, vermelho abaixo de 10 (pontos percentuais). */
function leitura_calibrar(string $setor, string $linha): void
{
    config()->set("drm.faixas-{$setor}.linhas.{$linha}", [
        'rotulo_referencia' => $linha,
        'sentido' => 'maior_melhor',
        'estado' => Faixas::CALIBRADA,
        'origem' => 'EBC — teste',
        'validado_em' => '2026-11-01',
        'verde' => ['min' => 50.0, 'max' => null],
        'amarelo' => ['min' => 10.0, 'max' => 50.0],
        'vermelho' => ['min' => null, 'max' => 10.0],
    ]);
}

describe('CA-1b — grau zero: todas as faixas nao_calibrada', function () {
    it('não há farol em linha nenhuma, nos três setores, e a leitura existe', function (Setor $setor) {
        $leitura = leitura_gerar(leitura_mes(), [], $setor);

        expect($leitura)->toBeInstanceOf(Leitura::class)
            ->and($leitura->farois)->toBe([])
            ->and($leitura->frases)->not->toBe([]);
    })->with([Setor::Industria, Setor::Comercio, Setor::Servicos]);

    it('a prosa se apoia só nos comparativos: abertura e o aviso de que não há histórico', function () {
        $leitura = leitura_gerar(leitura_mes());

        expect($leitura->frases)->toBe([
            'Em outubro de 2026, o negócio vendeu R$ 10.000,00 e sobraram R$ 2.900,00 depois de pagar todas as contas, inclusive a sua retirada.',
            'Este é o primeiro mês lançado, então ainda não há com o que comparar. A partir do próximo, esta leitura passa a dizer o que mudou.',
        ]);
    });

    it('CA-3 — mesma entrada, mesma saída', function () {
        $anterior = leitura_mes('2026-09', ['L4' => 250_000, 'L11' => 340_000]);

        expect(leitura_gerar(leitura_mes(), [$anterior]))->toEqual(leitura_gerar(leitura_mes(), [$anterior]));
    });
});

describe('CA-1 — farol só onde há faixa calibrada, por setor', function () {
    it('cada linha com faixa recebe verde, amarelo ou vermelho conforme o percentual, em cada setor', function (string $setor, string $linha) {
        leitura_calibrar($setor, $linha);

        foreach ([['60.0000', 'verde'], ['10.0000', 'amarelo'], ['9.9999', 'vermelho'], ['50.0000', 'verde'], ['49.9999', 'amarelo']] as [$percentual, $cor]) {
            $mes = leitura_mes('2026-10', [], [$linha => $percentual, 'L1' => '100.0000']);

            expect(leitura_gerar($mes, [], Setor::from($setor))->farois)->toBe([$linha => $cor]);
        }
    })->with(function () {
        foreach (['industria', 'comercio', 'servicos'] as $setor) {
            foreach (['L4', 'L5', 'L6', 'L6a', 'L6b', 'L7', 'L8', 'L10', 'L11'] as $linha) {
                yield "{$setor} {$linha}" => [$setor, $linha];
            }
        }
    });

    it('as faixas são por setor: calibrar o comércio não acende farol na indústria', function () {
        leitura_calibrar('comercio', 'L5');

        expect(leitura_gerar(leitura_mes(), [], Setor::Comercio)->farois)->toBe(['L5' => 'verde'])
            ->and(leitura_gerar(leitura_mes(), [], Setor::Industria)->farois)->toBe([]);
    });

    it('CA-1c — calibrar é editar config: a linha recém-calibrada passa a ter farol sem mudar lógica', function () {
        $mes = leitura_mes();
        expect(leitura_gerar($mes)->farois)->toBe([]);

        leitura_calibrar('comercio', 'L11'); // 29% → amarelo

        expect(leitura_gerar($mes)->farois)->toBe(['L11' => 'amarelo']);
    });

    it('linha sem percentual no mês não recebe farol, mesmo com faixa calibrada', function () {
        leitura_calibrar('comercio', 'L6a');
        $mes = leitura_mes('2026-10', [], ['L1' => '100.0000']);

        expect(leitura_gerar($mes)->farois)->toBe([]);
    });
});

describe('CA-3, CA-7 — a leitura de cada farol, e a reserva quando falta o texto', function () {
    it('usa o texto do pacote para a combinação linha e farol', function () {
        leitura_calibrar('comercio', 'L5');
        config()->set('drm.leitura.por_linha_e_farol.L5.verde', 'Sua margem bruta está folgada.');

        expect(leitura_gerar(leitura_mes())->frases)->toContain('Sua margem bruta está folgada.');
    });

    it('lacuna editorial: mensagem neutra de reserva, nunca vazio, e registra para o PO', function () {
        $inventario = sys_get_temp_dir().'/lacunas-leitura-'.uniqid().'.md';
        config()->set('drm.lacunas_path', $inventario);
        leitura_calibrar('comercio', 'L5');

        $frases = leitura_gerar(leitura_mes())->frases;

        expect($frases)->toContain('“Lucro Bruto”: farol verde.')
            ->and(array_filter($frases, static fn (string $f): bool => trim($f) === ''))->toBe([])
            ->and((string) file_get_contents($inventario))
            ->toContain('- `leitura` → `por_linha_e_farol.L5.verde` — **sem texto**');

        // Idempotente: ler de novo não duplica a linha.
        leitura_gerar(leitura_mes());
        expect(substr_count((string) file_get_contents($inventario), 'por_linha_e_farol.L5.verde'))->toBe(1);
        @unlink($inventario);
    });

    it('cada cor tem a sua reserva, e entram do vermelho ao verde', function () {
        foreach (['L4' => '5.0000', 'L6' => '20.0000', 'L11' => '70.0000'] as $linha => $percentual) {
            leitura_calibrar('comercio', $linha);
        }
        $mes = leitura_mes('2026-10', [], ['L1' => '100.0000', 'L4' => '5.0000', 'L6' => '20.0000', 'L11' => '70.0000']);

        $frases = leitura_gerar($mes)->frases;

        expect(array_slice($frases, -3))->toBe([
            '“Custo das Mercadorias Vendidas (CMV)”: farol vermelho.',
            '“Despesas Operacionais”: farol amarelo.',
            '“Lucro Líquido do Mês”: farol verde.',
        ]);
    });

    it('com a posição "dentro", o rótulo da reserva é o do modo', function () {
        leitura_calibrar('comercio', 'L7');

        $frases = leitura_gerar(leitura_mes('2026-10', [], ['L1' => '100.0000', 'L7' => '60.0000']), [], Setor::Comercio, 'dentro')->frases;

        expect($frases)->toContain('“Resultado Operacional (EBITDA)”: farol verde.');
    });

    it('frase-modelo ausente é erro de configuração, não texto improvisado', function () {
        config()->set('drm.leitura_frases.abertura.lucro', '');

        expect(fn () => leitura_gerar(leitura_mes()))->toThrow(LogicException::class, 'abertura.lucro');
    });
});

describe('CA-5 — prejuízo', function () {
    it('diz quanto faltou e aponta a linha que mais pesou, sem julgar', function () {
        // Vendeu 10.000, saiu 12.000: resultado −2.000. Maior saída: custo (L4) 7.000.
        $mes = leitura_mes('2026-10', ['L4' => 700_000, 'L11' => -200_000], ['L1' => '100.0000', 'L4' => '70.0000', 'L11' => '-20.0000']);

        $frases = leitura_gerar($mes)->frases;

        expect($frases[0])->toBe('Em outubro de 2026, o negócio vendeu R$ 10.000,00, mas o que saiu passou do que entrou em R$ 2.000,00.')
            ->and($frases[1])->toBe('A linha que mais pesou foi “Custo das Mercadorias Vendidas (CMV)”, com R$ 7.000,00 (70,0% do que o negócio vendeu).')
            ->and(implode(' ', $frases))->not->toMatch('/culpa|erro|péssim|ruim|irrespons/iu');
    });

    it('sem percentual da linha, a frase vem sem o parêntese', function () {
        $mes = leitura_mes('2026-10', ['L4' => 700_000, 'L11' => -200_000], ['L1' => '100.0000']);

        expect(leitura_gerar($mes)->frases[1])->toBe('A linha que mais pesou foi “Custo das Mercadorias Vendidas (CMV)”, com R$ 7.000,00.');
    });

    it('empate fica com a primeira linha na ordem da conta', function () {
        $mes = leitura_mes('2026-10', ['L2' => 500_000, 'L4' => 500_000, 'L11' => -200_000]);

        expect(leitura_gerar($mes)->frases[1])->toContain('Deduções da Receita Bruta');
    });

    it('prejuízo sem nenhuma saída registrada: só a abertura, sem inventar a linha pesada', function () {
        $mes = leitura_mes('2026-10', ['L2' => 0, 'L4' => 0, 'L6' => 0, 'L8' => 0, 'L10' => 0, 'L11' => -1]);

        expect(leitura_gerar($mes)->frases[0])->toContain('o que saiu passou do que entrou')
            ->and(implode(' ', leitura_gerar($mes)->frases))->not->toContain('mais pesou');
    });

    it('resultado zero é dito como zero a zero', function () {
        expect(leitura_gerar(leitura_mes('2026-10', ['L11' => 0]))->frases[0])
            ->toBe('Em outubro de 2026, o negócio vendeu R$ 10.000,00 e fechou no zero a zero: o que entrou cobriu exatamente o que saiu.');
    });
});

describe('CA-6 — sem faturamento', function () {
    it('não há farol de margem, mesmo com tudo calibrado, e a leitura fala do custo de manter o negócio', function () {
        foreach (['L4', 'L5', 'L6', 'L7', 'L11'] as $linha) {
            leitura_calibrar('comercio', $linha);
        }
        // Sem vendas: percentuais indisponíveis. Despesas 2.000 + financeiras 100 + retirada 1.000 = 3.100.
        $mes = leitura_mes('2026-10', ['L1' => 0, 'L2' => 0, 'L4' => 0, 'L11' => -310_000], []);

        $leitura = leitura_gerar($mes);

        expect($leitura->farois)->toBe([])
            ->and($leitura->frases)->toBe([
                'Em outubro de 2026 não houve faturamento registrado. Sem vendas não existe margem para medir, então os percentuais sobre a receita não se aplicam.',
                'Mesmo sem vender, manter o negócio funcionando custou R$ 3.100,00 neste mês (despesas de funcionamento, despesas financeiras e a retirada do dono).',
                'A linha que mais pesou foi “Despesas Operacionais”, com R$ 2.000,00.',
            ]);
    });

    it('não força interpretação: nem comparativos com meses anteriores', function () {
        $anterior = leitura_mes('2026-09');
        $mes = leitura_mes('2026-10', ['L1' => 0], []);

        expect(implode(' ', leitura_gerar($mes, [$anterior])->frases))->not->toContain('anterior')->not->toContain('primeiro mês');
    });

    it('mês parado sem nenhum custo: só diz que não houve faturamento', function () {
        $mes = leitura_mes('2026-10', ['L1' => 0, 'L2' => 0, 'L4' => 0, 'L6' => 0, 'L8' => 0, 'L10' => 0, 'L11' => 0], []);

        expect(leitura_gerar($mes)->frases)->toHaveCount(1);
    });
});

describe('CA-4 — a leitura usa o histórico', function () {
    it('diz quanto o resultado mudou e o que mais explica a diferença', function () {
        // Setembro: custo 2.500, resultado 3.400. Outubro: custo 3.000, resultado 2.900 → −14,7%.
        $setembro = leitura_mes('2026-09', ['L4' => 250_000, 'L11' => 340_000]);

        $frases = leitura_gerar(leitura_mes(), [$setembro])->frases;

        expect($frases[1])->toBe('O resultado do mês ficou 14,7% abaixo do de setembro.')
            ->and($frases[2])->toBe('O que mais explica a diferença é a linha “Custo das Mercadorias Vendidas (CMV)”, que foi de R$ 2.500,00 em setembro para R$ 3.000,00 neste mês.');
    });

    it('resultado acima do mês anterior', function () {
        $setembro = leitura_mes('2026-09', ['L1' => 800_000, 'L11' => 145_000]);

        $frases = leitura_gerar(leitura_mes(), [$setembro])->frases;

        // (2.900 − 1.450) / 1.450 = +100,0%; o que mais mudou foi a receita (+2.000).
        expect($frases[1])->toBe('O resultado do mês ficou 100,0% acima do de setembro.')
            ->and($frases[2])->toContain('“Receita Bruta”')->toContain('R$ 8.000,00 em setembro para R$ 10.000,00');
    });

    it('resultado igual ao do mês anterior, e nada a explicar', function () {
        $frases = leitura_gerar(leitura_mes(), [leitura_mes('2026-09')])->frases;

        expect($frases[1])->toBe('O resultado do mês ficou igual ao de setembro.')
            ->and(implode(' ', $frases))->not->toContain('explica a diferença');
    });

    it('variação que arredonda para zero conta como igual, não como queda', function () {
        // −0,04% → "0,0" na tela.
        $setembro = leitura_mes('2026-09', ['L11' => 290_100, 'L10' => 99_900]);

        expect(leitura_gerar(leitura_mes(), [$setembro])->frases[1])->toBe('O resultado do mês ficou igual ao de setembro.');
    });

    it('com três ou mais meses anteriores, compara também com a média', function () {
        $meses = [
            leitura_mes('2026-07', ['L11' => 200_000]),
            leitura_mes('2026-08', ['L11' => 200_000]),
            leitura_mes('2026-09', ['L11' => 200_000]),
        ];

        // média 2.000; outubro 2.900 → +45,0%
        expect(leitura_gerar(leitura_mes(), $meses)->frases)->toContain('Frente à média dos meses anteriores, o resultado está 45,0% acima.');
    });

    it('média: abaixo e igual', function () {
        $alto = [leitura_mes('2026-07', ['L11' => 400_000]), leitura_mes('2026-08', ['L11' => 400_000]), leitura_mes('2026-09', ['L11' => 400_000])];
        $igual = [leitura_mes('2026-07'), leitura_mes('2026-08'), leitura_mes('2026-09')];

        expect(leitura_gerar(leitura_mes(), $alto)->frases)->toContain('Frente à média dos meses anteriores, o resultado está 27,5% abaixo.')
            ->and(leitura_gerar(leitura_mes(), $igual)->frases)->toContain('O resultado está igual à média dos meses anteriores.');
    });

    it('com menos de três meses, não há média e a leitura não a inventa', function () {
        expect(implode(' ', leitura_gerar(leitura_mes(), [leitura_mes('2026-09', ['L11' => 100_000])])->frases))->not->toContain('média');
    });

    it('sem histórico: diz que é o primeiro mês e não inventa tendência', function () {
        $frases = leitura_gerar(leitura_mes())->frases;

        expect($frases)->toHaveCount(2)
            ->and($frases[1])->toContain('primeiro mês lançado')
            ->and(implode(' ', $frases))->not->toMatch('/acima|abaixo|tendência|subiu|caiu/u');
    });

    it('mês anterior sem lançamento: diz qual mês faltou', function () {
        $frases = leitura_gerar(leitura_mes('2026-10'), [leitura_mes('2026-08')])->frases;

        expect($frases[1])->toBe('O mês anterior (setembro) não foi lançado, então não dá para dizer o que mudou em relação a ele.');
    });

    it('mês anterior sem lançamento, mas com média possível: diz as duas coisas', function () {
        $meses = [leitura_mes('2026-06', ['L11' => 200_000]), leitura_mes('2026-07', ['L11' => 200_000]), leitura_mes('2026-08', ['L11' => 200_000])];

        $frases = leitura_gerar(leitura_mes('2026-10'), $meses)->frases;

        expect($frases[1])->toContain('não foi lançado')
            ->and($frases[2])->toBe('Frente à média dos meses anteriores, o resultado está 45,0% acima.');
    });

    it('resultado do mês anterior zerado: a variação em porcentagem não se aplica', function () {
        $frases = leitura_gerar(leitura_mes(), [leitura_mes('2026-09', ['L11' => 0])])->frases;

        expect($frases[1])->toBe('O resultado de setembro foi zero, então a variação em porcentagem não se aplica.');
    });

    it('mês anterior que não informou o resultado cai na frase padrão', function () {
        $frases = leitura_gerar(leitura_mes(), [leitura_mes('2026-09', ['L11' => null])])->frases;

        expect($frases[1])->toBe('Não foi possível comparar este mês com o anterior.');
    });

    it('a causa pode ser "outras receitas e despesas", que pode ser negativa', function () {
        $setembro = leitura_mes('2026-09', ['L7b' => 50_000, 'L11' => 340_000]);
        $outubro = leitura_mes('2026-10', ['L7b' => -20_000, 'L11' => 270_000]);

        expect(leitura_gerar($outubro, [$setembro])->frases[2])
            ->toContain('“Outras receitas e despesas”')->toContain('R$ 500,00 em setembro para −R$ 200,00 neste mês');
    });

    it('causa de uma linha que o mês anterior não informou é ignorada', function () {
        $setembro = leitura_mes('2026-09', ['L4' => null, 'L11' => 340_000, 'L10' => 0]);

        // Sem L4 no mês anterior, a maior mudança entre as informadas é a retirada (L10: 0 → 1.000).
        expect(leitura_gerar(leitura_mes(), [$setembro])->frases[2])->toContain('“Pró-labore”');
    });
});

describe('snapshot sem o que ler', function () {
    it('sem linhas de nível 1: não há leitura', function () {
        expect(leitura_gerar(leitura_mes('2026-10', [], null, [])))->toBeNull();
    });

    it('resultado final não informado: não há leitura', function () {
        expect(leitura_gerar(leitura_mes('2026-10', ['L11' => null])))->toBeNull();
    });

    it('receita não informada: não há leitura', function () {
        expect(leitura_gerar(leitura_mes('2026-10', ['L1' => null])))->toBeNull();
    });
});
