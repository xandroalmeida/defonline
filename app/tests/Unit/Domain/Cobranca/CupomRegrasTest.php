<?php

declare(strict_types=1);

use App\Domain\Cobranca\CupomRegras;
use App\Domain\Cobranca\MotivoRecusaCupom;
use App\Domain\Cobranca\TipoDesconto;
use Illuminate\Support\Carbon;

/**
 * STORY-072 / spec §2.5 — regras puras do cupom.
 */
describe('código', function () {
    it('normaliza para maiúsculas e sem espaços nas pontas', function () {
        expect(CupomRegras::normalizarCodigo('  beta-25 '))->toBe('BETA-25')
            ->and(CupomRegras::normalizarCodigo('promoção'))->toBe('PROMOÇÃO');
    });
});

describe('desconto', function () {
    it('calcula o percentual em inteiros, arredondando meio centavo para cima', function (int $valor, int $preco, int $esperado) {
        expect(TipoDesconto::Percentual->desconto($valor, $preco))->toBe($esperado);
    })->with([
        'metade' => [50, 4990, 2495],
        'arredonda para cima' => [10, 4995, 500],
        'arredonda para baixo' => [10, 4994, 499],
        'um por cento' => [1, 4990, 50],
        'total' => [100, 4990, 4990],
        'preço zero' => [50, 0, 0],
    ]);

    it('o valor fixo nunca passa do preço', function () {
        expect(TipoDesconto::ValorFixo->desconto(1000, 4990))->toBe(1000)
            ->and(TipoDesconto::ValorFixo->desconto(9999, 4990))->toBe(4990)
            ->and(TipoDesconto::ValorFixo->desconto(4990, 4990))->toBe(4990);
    });

    it('preço negativo ou valor negativo não geram desconto negativo', function () {
        expect(TipoDesconto::ValorFixo->desconto(100, -5))->toBe(0)
            ->and(TipoDesconto::ValorFixo->desconto(-100, 4990))->toBe(0);
    });

    it('formata e rotula', function () {
        expect(TipoDesconto::Percentual->formatar(20))->toBe('20%')
            ->and(TipoDesconto::ValorFixo->formatar(123456))->toBe('R$ 1.234,56')
            ->and(TipoDesconto::Percentual->rotulo())->toBe('Percentual')
            ->and(TipoDesconto::ValorFixo->rotulo())->toBe('Valor fixo');
    });
});

describe('definição', function () {
    $d = fn (string $data) => Carbon::parse($data);

    it('aceita as pontas válidas', function (TipoDesconto $tipo, int $valor) use ($d) {
        expect(CupomRegras::errosDaDefinicao($tipo, $valor, $d('2026-11-01'), $d('2026-11-01')))->toBe([]);
    })->with([
        'percentual 1' => [TipoDesconto::Percentual, 1],
        'percentual 100' => [TipoDesconto::Percentual, 100],
        'valor fixo 1 centavo' => [TipoDesconto::ValorFixo, 1],
        'valor fixo alto, acima de 100' => [TipoDesconto::ValorFixo, 250000],
    ]);

    it('recusa percentual fora de 1 a 100', function (int $valor) use ($d) {
        expect(CupomRegras::errosDaDefinicao(TipoDesconto::Percentual, $valor, $d('2026-11-01'), $d('2026-12-01')))
            ->toBe(['valor' => 'O percentual deve ficar entre 1 e 100.']);
    })->with([0, -5, 101, 1000]);

    it('recusa valor fixo menor ou igual a zero', function (int $valor) use ($d) {
        expect(CupomRegras::errosDaDefinicao(TipoDesconto::ValorFixo, $valor, $d('2026-11-01'), $d('2026-12-01')))
            ->toBe(['valor' => 'O valor fixo deve ser maior que zero.']);
    })->with([0, -1]);

    it('recusa a vigência invertida e acumula erros', function () use ($d) {
        expect(CupomRegras::errosDaDefinicao(TipoDesconto::Percentual, 0, $d('2026-12-02'), $d('2026-12-01')))
            ->toBe(['valor' => 'O percentual deve ficar entre 1 e 100.', 'vigencia_fim' => 'O fim da vigência não pode ser anterior ao início.']);
    });
});

describe('uso', function () {
    $avaliar = fn (array $o = []) => CupomRegras::avaliarUso(
        $o['ativo'] ?? true,
        Carbon::parse($o['inicio'] ?? '2026-11-01'),
        Carbon::parse($o['fim'] ?? '2026-11-30'),
        array_key_exists('limite', $o) ? $o['limite'] : 10,
        $o['usos'] ?? 0,
        $o['coberto'] ?? true,
        $o['jaUsou'] ?? false,
        Carbon::parse($o['agora'] ?? '2026-11-15 10:00'),
    );

    it('libera um cupom válido', function () use ($avaliar) {
        expect($avaliar())->toBeNull();
    });

    it('vale nos dois dias das pontas, inclusive no fim do dia', function (string $agora) use ($avaliar) {
        expect($avaliar(['agora' => $agora]))->toBeNull();
    })->with(['2026-11-01 00:00', '2026-11-30 23:59:59']);

    it('recusa fora da vigência', function (string $agora) use ($avaliar) {
        expect($avaliar(['agora' => $agora]))->toBe(MotivoRecusaCupom::ForaDaVigencia);
    })->with(['2026-10-31 23:59', '2026-12-01 00:00']);

    it('recusa cupom desativado antes de qualquer outra regra', function () use ($avaliar) {
        expect($avaliar(['ativo' => false, 'agora' => '2027-01-01']))->toBe(MotivoRecusaCupom::Inativo);
    });

    it('respeita o limite total de usos, sendo o último uso permitido', function () use ($avaliar) {
        expect($avaliar(['limite' => 10, 'usos' => 9]))->toBeNull()
            ->and($avaliar(['limite' => 10, 'usos' => 10]))->toBe(MotivoRecusaCupom::LimiteEsgotado)
            ->and($avaliar(['limite' => null, 'usos' => 1000000]))->toBeNull();
    });

    it('recusa item não coberto e quem já usou', function () use ($avaliar) {
        expect($avaliar(['coberto' => false]))->toBe(MotivoRecusaCupom::ItemNaoCoberto)
            ->and($avaliar(['jaUsou' => true]))->toBe(MotivoRecusaCupom::JaUtilizado);
    });

    it('todo motivo tem mensagem', function () {
        foreach (MotivoRecusaCupom::cases() as $motivo) {
            expect($motivo->mensagem())->not->toBe('');
        }
    });
});
