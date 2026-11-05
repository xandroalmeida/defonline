<?php

declare(strict_types=1);

use App\Support\Drm\DemonstracaoVisao;

/*
|-------------------------------------------------------------------------
| STORY-049 — o snapshot vira texto de tela sem passar pelo motor.
|
| Os cenários vêm do golden congelado da STORY-048 (a mesma saída que o motor
| grava), e o CA-8b é provado com snapshots de estrutura diferente da atual.
|-------------------------------------------------------------------------
*/

function snapshotGolden(string $cenario): array
{
    return json_decode((string) file_get_contents(base_path("tests/Fixtures/Drm/golden/{$cenario}.json")), true, 512, JSON_THROW_ON_ERROR)['esperado'];
}

/** @return list<string> */
function codigos(DemonstracaoVisao $v): array
{
    return array_map(fn ($l) => $l->codigo, $v->linhas);
}

function linhaDe(DemonstracaoVisao $v, string $codigo)
{
    return collect($v->linhas)->firstWhere('codigo', $codigo);
}

function bruta(string $codigo, array $extra = []): array
{
    return array_merge([
        'codigo' => $codigo, 'nivel' => 1, 'rotulo' => "Rótulo {$codigo}", 'tipo' => 'item', 'destaque' => false,
        'nominal' => '10.00', 'percentual' => '10.0000', 'motivo' => null, 'mensagem' => null,
        'motivo_nominal' => null, 'mensagem_nominal' => null,
    ], $extra);
}

describe('CA-2 — linhas na ordem canônica', function () {
    it('modo fora: as 15 linhas na ordem do snapshot, com o nível e o tipo de cada uma', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('saudavel_comercio'), 'fora');

        expect($v->posicao)->toBe('fora')
            ->and(codigos($v))->toBe(['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L6a', 'L6b', 'L7', 'L7b', 'L8', 'L8a', 'L8b', 'L9', 'L10', 'L11'])
            ->and(linhaDe($v, 'L3')->tipo)->toBe('total')
            ->and(linhaDe($v, 'L6a')->nivel)->toBe(2);
    });

    it('modo dentro: L9 e L10 não existem e o pró-labore (L6c) é item de nível 3', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('saudavel_comercio'), 'dentro');

        expect($v->posicao)->toBe('dentro')
            ->and(codigos($v))->toBe(['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L6a', 'L6b', 'L6c', 'L7', 'L7b', 'L8', 'L8a', 'L8b', 'L11'])
            ->and(linhaDe($v, 'L6c')->nivel)->toBe(3)
            ->and(linhaDe($v, 'L7')->rotulo)->toBe('Resultado Operacional (EBITDA)');
    });

    it('financeiras agregadas: L8a e L8b (sem nominal, motivo grupo_agregado) não são exibidas como itens', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('todos_agregados_industria'), 'fora');

        expect(codigos($v))->not->toContain('L8a')->not->toContain('L8b')
            ->and(codigos($v))->toContain('L8');
    });

    it('subtotal de nível 2 com nominal continua sendo exibido', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('todos_agregados_industria'), 'fora');

        expect(codigos($v))->toContain('L6a')->toContain('L6b');
    });

    it('L7b presente quando há valor e ausente quando D41 e D42 são zero (o motor decide, a tela só desenha)', function () {
        $com = DemonstracaoVisao::de(snapshotGolden('saudavel_comercio'), 'fora');
        $sem = DemonstracaoVisao::de(snapshotGolden('sem_prolabore_sem_nao_operacionais'), 'fora');

        expect(codigos($com))->toContain('L7b')
            ->and(codigos($sem))->not->toContain('L7b');
    });
});

describe('CA-3 — nominal e percentual em toda linha, no padrão BR', function () {
    it('formata reais com milhar e vírgula e percentual com uma casa', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('saudavel_comercio'), 'fora');

        expect(linhaDe($v, 'L1')->nominal)->toBe('R$ 100.000,00')
            ->and(linhaDe($v, 'L1')->nominalSemMoeda)->toBe('100.000,00')
            ->and(linhaDe($v, 'L1')->percentual)->toBe('100,0%')
            ->and(linhaDe($v, 'L5')->nominal)->toBe('R$ 58.000,00')
            ->and(linhaDe($v, 'L5')->percentual)->toBe('58,0%')
            ->and(linhaDe($v, 'L7')->nominal)->toBe('R$ 35.199,25')
            ->and(linhaDe($v, 'L7')->percentual)->toBe('35,2%');
    });

    it('toda linha exibida tem o par: ou os dois, ou o motivo de não ter', function () {
        foreach (['saudavel_comercio', 'prejuizo_comercio_agregado', 'todos_agregados_industria', 'sem_faturamento_servicos'] as $cenario) {
            foreach (['fora', 'dentro'] as $posicao) {
                foreach (DemonstracaoVisao::de(snapshotGolden($cenario), $posicao)->linhas as $l) {
                    expect($l->nominal !== null || $l->nominalAusente !== null)->toBeTrue("{$cenario}/{$posicao}/{$l->codigo} sem nominal nem motivo")
                        ->and($l->percentual !== null || $l->percentualAusente !== null)->toBeTrue("{$cenario}/{$posicao}/{$l->codigo} sem percentual nem motivo");
                }
            }
        }
    });

    it('o lucro líquido exibido é o mesmo nos dois modos (nominal e percentual)', function () {
        foreach (['saudavel_comercio', 'prejuizo_comercio_agregado', 'todos_agregados_industria', 'sem_faturamento_servicos'] as $cenario) {
            $fora = linhaDe(DemonstracaoVisao::de(snapshotGolden($cenario), 'fora'), 'L11');
            $dentro = linhaDe(DemonstracaoVisao::de(snapshotGolden($cenario), 'dentro'), 'L11');

            expect($fora->nominal)->toBe($dentro->nominal)->and($fora->percentual)->toBe($dentro->percentual);
        }
    });

    it('o prefixo contábil vem do tipo da linha, e L7b é ± e não –', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('saudavel_comercio'), 'fora');

        expect(linhaDe($v, 'L1')->prefixo)->toBe('')
            ->and(linhaDe($v, 'L2')->prefixo)->toBe('(–) ')
            ->and(linhaDe($v, 'L3')->prefixo)->toBe('= ')
            ->and(linhaDe($v, 'L6a')->prefixo)->toBe('')
            ->and(linhaDe($v, 'L7b')->prefixo)->toBe('(±) ');
    });
});

describe('CA-4 — os três números', function () {
    it('modo fora: vendeu, sobrou antes da retirada, sobrou depois', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('saudavel_comercio'), 'fora');

        expect(array_column($v->destaques, 'rotulo'))->toBe(['Quanto vendeu', 'Sobrou antes da sua retirada', 'Sobrou depois da sua retirada'])
            ->and(array_map(fn ($d) => $d['linha']->codigo, $v->destaques))->toBe(['L1', 'L9', 'L11']);
    });

    it('modo dentro: o segundo destaque passa a ser o EBITDA', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('saudavel_comercio'), 'dentro');

        expect(array_column($v->destaques, 'rotulo'))->toBe(['Quanto vendeu', 'Resultado operacional (EBITDA)', 'Sobrou depois da sua retirada'])
            ->and($v->destaques[1]['linha']->nominal)->toBe('R$ 27.199,25');
    });
});

describe('CA-5 — sem faturamento', function () {
    it('percentuais indisponíveis com o motivo em texto acessível; valores nominais presentes', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('sem_faturamento_servicos'), 'fora');

        expect($v->semFaturamento)->toContain('Sem faturamento neste mês')
            ->and(linhaDe($v, 'L6')->percentual)->toBeNull()
            ->and(linhaDe($v, 'L6')->percentualAusente)->toBe('Percentual indisponível: sem faturamento neste mês')
            ->and(linhaDe($v, 'L6')->nominal)->not->toBeNull();
        foreach ($v->linhas as $l) {
            expect($l->percentual)->toBeNull();
        }
    });

    it('com faturamento não há aviso', function () {
        expect(DemonstracaoVisao::de(snapshotGolden('saudavel_comercio'), 'fora')->semFaturamento)->toBeNull();
    });
});

describe('CA-6 — prejuízo', function () {
    it('sinal de menos real, destaque com o verbo trocado e a linha final marcada', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('prejuizo_comercio_agregado'), 'fora');
        $final = $v->destaques[2];

        expect($v->prejuizo)->toBeTrue()
            ->and($final['rotulo'])->toBe('Faltou depois da sua retirada')
            ->and($final['linha']->nominal)->toStartWith('−R$ ')
            ->and($final['linha']->percentual)->toStartWith('−')
            ->and($final['linha']->negativo)->toBeTrue()
            ->and($final['linha']->prejuizo)->toBeTrue();
    });

    it('lucro não marca prejuízo', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('saudavel_comercio'), 'fora');

        expect($v->prejuizo)->toBeFalse()->and(linhaDe($v, 'L11')->negativo)->toBeFalse();
    });

    it('prejuízo sem faturamento: valor com sinal e percentual indisponível', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('sem_faturamento_servicos'), 'fora');
        $final = linhaDe($v, 'L11');

        expect($final->nominal)->toStartWith('−R$ ')->and($final->percentual)->toBeNull()->and($v->destaques)->toHaveCount(3);
    });
});

describe('cards por trecho da conta (DDR-004 item 4)', function () {
    it('um card fecha em cada totalizador, com o nome do trecho', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('saudavel_comercio'), 'fora');

        expect(array_column($v->trechos, 'titulo'))->toBe(['Receita', 'Custo e lucro bruto', 'Despesas operacionais', 'Financeiras', 'Resultado'])
            ->and(array_map(fn ($t) => array_map(fn ($l) => $l->codigo, $t['linhas']), $v->trechos)[2])->toBe(['L6', 'L6a', 'L6b', 'L7']);
    });

    it('modo dentro: o último card tem o seu próprio nome', function () {
        $v = DemonstracaoVisao::de(snapshotGolden('saudavel_comercio'), 'dentro');

        expect(array_column($v->trechos, 'titulo'))->toBe(['Receita', 'Custo e lucro bruto', 'Despesas operacionais', 'Financeiras e resultado']);
    });
});

describe('CA-8b — render genérico: estrutura diferente da atual não derruba', function () {
    it('código desconhecido, tipo novo e nível 4 renderizam no card corrente', function () {
        $v = DemonstracaoVisao::de(['linhas' => ['fora' => [
            bruta('Z1', ['destaque' => true, 'percentual' => '100.0000']),
            bruta('Z2', ['tipo' => 'novo', 'nivel' => 4]),
            bruta('Z3', ['tipo' => 'total']),
            bruta('Z4'),
        ]]], 'fora');

        expect(codigos($v))->toBe(['Z1', 'Z2', 'Z3', 'Z4'])
            ->and(array_column($v->trechos, 'titulo'))->toBe(['Rótulo Z3', 'Demais linhas'])
            ->and($v->trechos[0]['linhas'])->toHaveCount(3)
            ->and(linhaDe($v, 'Z2')->prefixo)->toBe('');
    });

    it('snapshot de geração antiga, sem tipo nem destaque, usa a regra declarativa do DDR-004', function () {
        $velha = fn (string $c, string $nominal, string $pct, int $nivel = 1) => ['codigo' => $c, 'nivel' => $nivel, 'rotulo' => "Linha {$c}", 'nominal' => $nominal, 'percentual' => $pct];
        $v = DemonstracaoVisao::de(['linhas' => ['fora' => [
            $velha('L1', '100.00', '100.0000'),
            $velha('L7', '30.00', '30.0000'),
            $velha('L9', '20.00', '20.0000'),
            $velha('L11', '10.00', '10.0000'),
        ]]], 'fora');

        expect(array_map(fn ($d) => $d['linha']->codigo, $v->destaques))->toBe(['L1', 'L9', 'L11'])
            ->and(linhaDe($v, 'L9')->tipo)->toBe('item');
    });

    it('sem L9, o segundo destaque é L7; sem âncora de 100%, o destaque falta e a tabela segue completa', function () {
        $v = DemonstracaoVisao::de(['linhas' => ['fora' => [
            ['codigo' => 'L7', 'nivel' => 1, 'rotulo' => 'Operacional', 'nominal' => '30.00', 'percentual' => '30.0000'],
            ['codigo' => 'L11', 'nivel' => 1, 'rotulo' => 'Final', 'nominal' => '10.00', 'percentual' => '10.0000'],
        ]]], 'fora');

        expect(array_map(fn ($d) => $d['linha']->codigo, $v->destaques))->toBe(['L7', 'L11'])->and($v->linhas)->toHaveCount(2);
    });

    it('a posição pedida ausente cai na que o snapshot tem; snapshot vazio ou malformado renderiza vazio', function () {
        $so = ['linhas' => ['dentro' => [bruta('L1', ['destaque' => true])]]];

        expect(DemonstracaoVisao::de($so, 'fora')->posicao)->toBe('dentro')
            ->and(DemonstracaoVisao::de([], 'fora')->linhas)->toBe([])
            ->and(DemonstracaoVisao::de([], 'fora')->destaques)->toBe([])
            ->and(DemonstracaoVisao::de(['linhas' => 'x'], 'fora')->trechos)->toBe([])
            ->and(DemonstracaoVisao::de(['linhas' => ['fora' => ['x', 1]]], 'fora')->linhas)->toBe([]);
    });

    it('campos faltando ou malformados viram padrão, nunca erro', function () {
        $v = DemonstracaoVisao::de(['linhas' => ['fora' => [
            ['codigo' => 'L1'],
            ['codigo' => 'L2', 'nominal' => 'abc', 'percentual' => 7, 'rotulo' => ''],
            [],
        ]]], 'fora');

        expect($v->linhas)->toHaveCount(3)
            ->and($v->linhas[0]->rotulo)->toBe('L1')
            ->and($v->linhas[0]->nominalAusente)->toBe('Valor indisponível: motivo não informado')
            ->and($v->linhas[1]->nominal)->toBeNull()
            ->and($v->linhas[1]->percentualAusente)->toBe('Percentual indisponível: motivo não informado')
            ->and($v->linhas[2]->codigo)->toBe('')
            ->and($v->linhas[2]->nivel)->toBe(1);
    });

    it('motivo desconhecido usa a mensagem do próprio snapshot', function () {
        $v = DemonstracaoVisao::de(['linhas' => ['fora' => [
            bruta('L1', ['percentual' => null, 'motivo' => 'indisponivel:novo', 'mensagem' => 'Motivo novo do motor.']),
        ]]], 'fora');

        expect($v->linhas[0]->percentualAusente)->toBe('Percentual indisponível: Motivo novo do motor.')
            ->and($v->semFaturamento)->toBeNull();
    });

    it('sem faturamento sem mensagem no snapshot usa o texto do pacote', function () {
        $v = DemonstracaoVisao::de(['linhas' => ['fora' => [
            bruta('L1', ['percentual' => null, 'motivo' => 'indisponivel:sem_faturamento']),
        ]]], 'fora');

        expect($v->semFaturamento)->toContain('Sem faturamento neste mês');
    });
});

describe('CA-8 — rodapé do snapshot', function () {
    it('versão do motor e data vêm do próprio snapshot', function () {
        $s = snapshotGolden('saudavel_comercio');
        $s['motor_drm_version'] = '7.3.1';
        $v = DemonstracaoVisao::de($s, 'fora');

        expect($v->motorVersion)->toBe('7.3.1')
            ->and($v->geradoEm?->toIso8601String())->toBe('2026-11-05T09:30:00+00:00');
    });

    it('versão e data ausentes ou ilegíveis viram null', function () {
        $v = DemonstracaoVisao::de(['gerado_em' => 'não é data'], 'fora');
        $w = DemonstracaoVisao::de(['gerado_em' => ''], 'fora');

        expect($v->motorVersion)->toBeNull()->and($v->geradoEm)->toBeNull()->and($w->geradoEm)->toBeNull();
    });
});

describe('CA-7 — ressalvas aceitas', function () {
    it('traduz cada regra aceita e preenche o valor registrado', function () {
        $textos = DemonstracaoVisao::ressalvas([
            ['regra' => 'A1', 'ocorrido_em' => 'x', 'valor_envolvido' => '8430.00'],
            ['regra' => 'A3', 'ocorrido_em' => 'x', 'valor_envolvido' => '3240.00'],
        ]);

        expect($textos)->toBe(['Você não informou nenhuma venda neste mês.', 'Este mês fechou com prejuízo de R$ 3.240,00.']);
    });

    it('regra desconhecida, entrada em formato simples e valor ausente não quebram', function () {
        $generica = (string) config('drm.tela.ressalva_generica');

        expect(DemonstracaoVisao::ressalvas([['regra' => 'A99'], 'A5', ['regra' => 'A3'], ['regra' => 'A3', 'valor_envolvido' => 'xx'], [], 7, ['regra' => '']]))
            ->toBe([$generica, 'Nenhuma retirada (pró-labore) foi lançada neste mês.', $generica, $generica])
            ->and(DemonstracaoVisao::ressalvas([]))->toBe([]);
    });
});
