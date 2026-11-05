<?php

declare(strict_types=1);

use App\Livewire\Drm\Demonstracao;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
|-------------------------------------------------------------------------
| STORY-051 CA-3, CA-4, CA-10 — os quatro comparativos por linha de nível 1, na Demonstração do Mês,
| e o acesso à série. Tudo derivado das versões vigentes na leitura.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-05 10:00:00');
    Carbon::setTestNow('2026-11-05 10:00:00');
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'cnae' => '4711301', 'nome_fantasia' => 'Padaria do Bairro']);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
});

function mesAberto(EmpresaAnalisada $empresa, string $competencia)
{
    return Livewire::test(Demonstracao::class, ['empresa' => $empresa, 'competencia' => $competencia]);
}

/** Texto visível de um item de comparativo (`dusk`), sem etiquetas. */
function itemDeComparativo($teste, string $linha, string $chave): string
{
    $html = preg_replace('/<!--.*?-->/s', '', $teste->html());
    preg_match('/dusk="drm-comparativo-'.$linha.'-'.$chave.'">(.*?)<\/div>/s', $html, $m);

    return trim((string) preg_replace('/\s+/', ' ', strip_tags($m[1] ?? '')));
}

describe('CA-3 — quatro comparativos por linha de nível 1', function () {
    it('cada linha de nível 1 ganha um bloco com os quatro comparativos', function () {
        serieMes($this->empresa, '2026-10');

        $c = mesAberto($this->empresa, '2026-10');

        $c->assertSeeHtml('dusk="drm-comparativos"')
            ->assertSee('Comparado aos outros meses')
            ->assertSeeHtml('dusk="drm-comparativo-L1"')
            ->assertSeeHtml('dusk="drm-comparativo-L11"')
            ->assertSee('Mês anterior')
            ->assertSee('Média dos meses anteriores')
            ->assertSee('Acumulado no ano')
            ->assertSee('Participação média sobre a receita');
        // Só nível 1: nenhum item (nível 2 ou 3) tem bloco.
        $c->assertDontSeeHtml('dusk="drm-comparativo-L6a"')->assertDontSeeHtml('dusk="drm-comparativo-L8a"');
    });

    it('quatro lançamentos: números reais nos quatro, sobre as versões vigentes', function () {
        serieMes($this->empresa, '2026-07', '2.900,00');   // lucro 2.000
        serieMes($this->empresa, '2026-08', '3.400,00');   // 1.500
        serieMes($this->empresa, '2026-09', '3.400,00');   // 1.500
        serieMes($this->empresa, '2026-10', '2.400,00');   // 2.500; receita 10.000

        $c = mesAberto($this->empresa, '2026-10');

        expect(itemDeComparativo($c, 'L11', 'anterior'))->toContain('+66,7% em relação a setembro')
            ->and(itemDeComparativo($c, 'L11', 'media'))->toContain('+50,0% (média de 3 meses lançados)')
            ->and(itemDeComparativo($c, 'L11', 'acumulado'))->toContain('R$ 7.500,00 (4 meses lançados em 2026)')
            // 20% + 15% + 15% + 25% de 10.000 → média 18,75%
            ->and(itemDeComparativo($c, 'L11', 'participacao'))->toContain('18,8% (média de 4 meses lançados)')
            ->and(itemDeComparativo($c, 'L1', 'participacao'))->toContain('100,0% (média de 4 meses lançados)');
    });
});

describe('CA-4 — cada comparativo diz por que não existe', function () {
    it('primeiro mês: anterior e média indisponíveis, acumulado e participação já existem', function () {
        serieMes($this->empresa, '2026-10');

        $c = mesAberto($this->empresa, '2026-10');

        expect(itemDeComparativo($c, 'L11', 'anterior'))->toContain('primeiro mês lançado')
            ->and(itemDeComparativo($c, 'L11', 'media'))->toContain('média disponível a partir do 4º mês lançado')
            ->and(itemDeComparativo($c, 'L11', 'acumulado'))->toContain('R$ 1.000,00 (1 meses lançados em 2026)')
            ->and(itemDeComparativo($c, 'L11', 'participacao'))->toContain('10,0%');
    });

    it('mês anterior não lançado: diz qual e nunca compara com zero', function () {
        serieMes($this->empresa, '2026-08');
        serieMes($this->empresa, '2026-10');

        expect(itemDeComparativo(mesAberto($this->empresa, '2026-10'), 'L11', 'anterior'))
            ->toContain('setembro não foi lançado, então não dá para comparar');
    });

    it('sem faturamento: a participação média diz isso em palavras', function () {
        $mes = serieMes($this->empresa, '2026-10', '0,00', ['D01' => '0,00', 'D02' => '0,00']);

        $c = mesAberto($this->empresa, '2026-10');

        expect(itemDeComparativo($c, 'L11', 'participacao'))->toContain('sem faturamento nos meses anteriores');
        expect($mes->demonstracao['linhas']['fora'][0]['percentual'])->toBeNull();
    });
});

describe('CA-10 — nada é recalculado, nada é persistido', function () {
    it('trocar o modo do pró-labore lê as linhas do outro modo, na mesma série', function () {
        serieMes($this->empresa, '2026-09', '3.900,00');
        serieMes($this->empresa, '2026-10', '3.400,00');

        $c = mesAberto($this->empresa, '2026-10');
        $c->assertSeeHtml('dusk="drm-comparativo-L9"');   // modo "fora": existe L9
        $c->call('trocarPosicao')->assertDontSeeHtml('dusk="drm-comparativo-L9"')->assertSeeHtml('dusk="drm-comparativo-L11"');
    });
});

describe('acesso à série', function () {
    it('a demonstração tem "Ver todos os meses" ligado à série', function () {
        serieMes($this->empresa, '2026-10');

        mesAberto($this->empresa, '2026-10')
            ->assertSee('Ver todos os meses')
            ->assertSeeHtml('href="'.route('drm.serie', $this->empresa).'"')
            ->assertSeeHtml('dusk="drm-demonstracao-ver-serie"');
    });

    it('a página da empresa tem "Ver todos os meses"', function () {
        $this->get(route('empresas.show', $this->empresa))
            ->assertSee('Ver todos os meses')
            ->assertSee(route('drm.serie', $this->empresa), false);
    });
});
