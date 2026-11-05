<?php

declare(strict_types=1);

use App\Livewire\Drm\Demonstracao;
use App\Livewire\Drm\Lancamento;
use App\Models\DrmLancamento;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
|-------------------------------------------------------------------------
| STORY-051 CA-5 e CA-6 — A4 (rubrica atípica) e A6 (salto de competência) no lançamento.
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

/** Abre o formulário da competência e preenche um mês simples (custo, vendas e financeiras agregados; administrativas detalhada). */
function lancarMesComD21(EmpresaAnalisada $empresa, string $competencia, string $d21)
{
    $c = Livewire::withQueryParams(['competencia' => $competencia])->test(Lancamento::class, ['empresa' => $empresa]);
    foreach (['custo', 'vendas', 'financeiras'] as $grupo) {
        $c->call('alternarModo', $grupo, 'agregado');
    }
    $c->call('alternarModo', 'administrativas', 'detalhado');
    foreach (['D01' => '10.000,00', 'D02' => '1.000,00', 'D03' => '3.900,00', 'D08' => '1.000,00', 'D37' => '100,00', 'D40' => '1.000,00', 'D21' => $d21] as $campo => $valor) {
        $c->set('valores.'.$campo, $valor);
    }
    while ($c->get('bloco_atual') < 6) {
        $c->call('proximo');
    }

    return $c;
}

describe('A4 — rubrica atípica no lançamento (CA-5)', function () {
    beforeEach(function () {
        foreach (['2026-07', '2026-08', '2026-09'] as $mes) {
            serieMes($this->empresa, $mes, '3.900,00', ['D21' => '0,00'], true);
        }
    });

    it('a partir do 4º lançamento, o campo acima de 3× a média pede aceite e explica a consequência', function () {
        $c = lancarMesComD21($this->empresa, '2026-10', '3.000,00');

        $c->assertSeeHtml('dusk="drm-lancamento-alerta-A4"')
            ->assertSee('Décimo-terceiro salário está bem acima da média')
            ->assertSee('R$ 3.000,00')
            ->assertSee('O mês fica marcado como atípico na série histórica')
            ->call('fechar');
        expect(DrmLancamento::query()->vigente()->count())->toBe(3);   // não fechou sem aceite

        $c->call('aceitarAlertas')->call('fechar');
        $l = DrmLancamento::query()->vigente()->where('competencia', '2026-10-01')->firstOrFail();
        expect(array_column($l->alertas_aceitos, 'regra'))->toContain('A4');
    });

    it('valor normal não dispara A4', function () {
        $c = lancarMesComD21($this->empresa, '2026-10', '0,00');

        $c->assertDontSeeHtml('dusk="drm-lancamento-alerta-A4"');
    });

    it('antes do 4º lançamento A4 não existe (2 meses de histórico)', function () {
        DrmLancamento::query()->where('competencia', '2026-07-01')->forceDelete();

        $c = lancarMesComD21($this->empresa, '2026-10', '3.000,00');

        $c->assertDontSeeHtml('dusk="drm-lancamento-alerta-A4"');
    });

    it('dois campos atípicos geram duas ocorrências, aceitas de uma vez, e a demonstração mostra UMA ressalva', function () {
        $c = lancarMesComD21($this->empresa, '2026-10', '3.000,00');
        $c->set('valores.D20', '2.000,00')->assertSeeHtml('dusk="drm-lancamento-alerta-A4"');
        $c->call('aceitarAlertas')->call('fechar');

        $l = DrmLancamento::query()->vigente()->where('competencia', '2026-10-01')->firstOrFail();
        expect(count(array_filter($l->alertas_aceitos, fn ($a) => $a['regra'] === 'A4')))->toBe(2);

        // O mês fecha no vermelho (A3) e tem A4 em dois campos: duas ressalvas, não três.
        $d = Livewire::test(Demonstracao::class, ['empresa' => $this->empresa, 'competencia' => '2026-10'])
            ->assertSee('Ressalvas registradas no lançamento (2)');
        expect(substr_count($d->html(), 'Uma despesa deste mês ficou bem acima da média dos meses anteriores.'))->toBe(1);
    });
});

describe('A6 — salto de competência (CA-6)', function () {
    it('lacuna entre o último lançamento e o atual é detectada e oferece lançar o mês que falta', function () {
        serieMes($this->empresa, '2026-07');

        $c = lancarMesComD21($this->empresa, '2026-10', '0,00');

        $c->assertSeeHtml('dusk="drm-lancamento-alerta-A6"')
            ->assertSee('Faltam meses entre um lançamento e outro')
            ->assertSee('Seu último lançamento foi julho/2026 e você está lançando outubro/2026. Ficaram 2 meses (agosto/2026 e setembro/2026) sem lançar.')
            ->assertSee('Mês não lançado não é mês zerado')
            ->assertSeeHtml('dusk="drm-lancamento-a6-lancar-faltante"')
            ->assertSeeHtml(route('drm.lancar', [$this->empresa, 'competencia' => '2026-08']));
    });

    it('é alerta: aceitando, fecha, e o mês faltante continua ausente (nunca vira zero)', function () {
        serieMes($this->empresa, '2026-08');

        $c = lancarMesComD21($this->empresa, '2026-10', '0,00');
        $c->call('fechar');
        expect(DrmLancamento::query()->vigente()->count())->toBe(1);

        $c->call('aceitarAlertas')->call('fechar');
        expect(DrmLancamento::query()->vigente()->count())->toBe(2)
            ->and(DrmLancamento::query()->where('competencia', '2026-09-01')->exists())->toBeFalse();
    });

    it('mês seguinte ao último lançado, ou primeiro lançamento: nenhum aviso', function () {
        $primeiro = lancarMesComD21($this->empresa, '2026-10', '0,00');
        $primeiro->assertDontSeeHtml('dusk="drm-lancamento-alerta-A6"');

        serieMes($this->empresa, '2026-09');
        // outubro já existe? não: a primeira abertura não gravou nada.
        lancarMesComD21($this->empresa, '2026-10', '0,00')->assertDontSeeHtml('dusk="drm-lancamento-alerta-A6"');
    });

    it('na retificação não há salto: o mês já existe', function () {
        serieMes($this->empresa, '2026-07');
        serieMes($this->empresa, '2026-10');

        $c = Livewire::test(Lancamento::class, ['empresa' => $this->empresa, 'competencia' => '2026-10']);
        while ($c->get('bloco_atual') < 6) {
            $c->call('proximo');
        }

        $c->assertDontSeeHtml('dusk="drm-lancamento-alerta-A6"');
    });
});
