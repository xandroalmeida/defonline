<?php

declare(strict_types=1);

use App\Domain\Drm\Faixas;
use App\Livewire\Drm\Demonstracao;
use App\Models\DrmLancamento;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Livewire\Livewire;

/*
|-------------------------------------------------------------------------
| STORY-054 — faróis e leitura na Demonstração do Mês, derivados na exibição (ADR-007).
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    config()->set('drm.lacunas_path', '');
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'cnae' => '4711301']);
});

function faroisAbrir(EmpresaAnalisada $empresa, string $competencia = '2026-10')
{
    return Livewire::test(Demonstracao::class, ['empresa' => $empresa, 'competencia' => $competencia]);
}

function faroisCalibrar(string $linha = 'L11', float $verdeAPartirDe = 50.0): void
{
    config()->set("drm.faixas-comercio.linhas.{$linha}", [
        'rotulo_referencia' => $linha, 'sentido' => 'maior_melhor', 'estado' => Faixas::CALIBRADA,
        'origem' => 'EBC — teste', 'validado_em' => '2026-11-01',
        'verde' => ['min' => $verdeAPartirDe, 'max' => null],
        'amarelo' => ['min' => 10.0, 'max' => $verdeAPartirDe],
        'vermelho' => ['min' => null, 'max' => 10.0],
    ]);
}

describe('CA-1b — grau zero', function () {
    it('renderiza a leitura, sem farol e sem placeholder de erro', function () {
        serieMes($this->empresa, '2026-10');

        $c = faroisAbrir($this->empresa);

        $c->assertSeeHtml('dusk="drm-leitura"')
            ->assertSee('O que este mês está dizendo')
            ->assertSee('Em outubro de 2026, o negócio vendeu R$ 10.000,00')
            ->assertSee('primeiro mês lançado')
            ->assertDontSeeHtml('data-farol')
            ->assertDontSee('Sem classificação');
        expect($c->html())->not->toContain(':linha')->not->toContain(':valor');
    });
});

describe('CA-1, CA-9 — farol calibrado', function () {
    it('aparece na linha, com ícone e texto além da cor, na tabela e nos cards', function () {
        serieMes($this->empresa, '2026-10'); // L11 = 10% → amarelo
        faroisCalibrar('L11', 60.0);

        $c = faroisAbrir($this->empresa);

        $c->assertSeeHtml('dusk="drm-farol-L11"')->assertSeeHtml('dusk="drm-cartao-farol-L11"')
            ->assertSeeHtml('data-farol="amarelo"')->assertSee('Amarelo');
        expect($c->html())->toContain('aria-label="Farol: Amarelo"')->toContain('<svg');
        // Só a linha calibrada recebe farol.
        $c->assertDontSeeHtml('dusk="drm-farol-L5"');
    });

    it('a leitura traz a reserva neutra quando falta o texto, e o texto do pacote quando existe', function () {
        serieMes($this->empresa, '2026-10');
        faroisCalibrar('L11', 60.0);

        faroisAbrir($this->empresa)->assertSee('“Lucro Líquido do Mês”: farol amarelo.');

        config()->set('drm.leitura.por_linha_e_farol.L11.amarelo', 'O lucro está apertado.');
        faroisAbrir($this->empresa)->assertSee('O lucro está apertado.');
    });
});

describe('CA-8 — interpretação derivada, valores imutáveis', function () {
    it('recalibrar muda o farol de um mês antigo e não toca nenhum valor do snapshot', function () {
        $mes = serieMes($this->empresa, '2026-10');
        $antes = DrmLancamento::query()->whereKey($mes->id)->first();
        $snapshot = $antes->getRawOriginal('demonstracao');
        $hash = $antes->payload_hash;

        faroisCalibrar('L11', 60.0); // 10% → amarelo
        $amarelo = faroisAbrir($this->empresa);
        $amarelo->assertSeeHtml('data-farol="amarelo"');

        faroisCalibrar('L11', 10.0); // 10% → verde
        $verde = faroisAbrir($this->empresa);
        $verde->assertSeeHtml('data-farol="verde"')->assertDontSeeHtml('data-farol="amarelo"');

        $depois = DrmLancamento::query()->whereKey($mes->id)->first();
        expect($depois->getRawOriginal('demonstracao'))->toBe($snapshot)
            ->and($depois->payload_hash)->toBe($hash)
            // E o que a tela mostra de valor é o mesmo nas duas leituras.
            ->and($amarelo->html())->toContain('R$ 10.000,00')->and($verde->html())->toContain('R$ 10.000,00');
    });

    it('o snapshot não guarda faixa, farol nem leitura', function () {
        $mes = serieMes($this->empresa, '2026-10');
        faroisCalibrar('L11', 60.0);
        faroisAbrir($this->empresa);

        $bruto = strtolower((string) DrmLancamento::query()->whereKey($mes->id)->first()->getRawOriginal('demonstracao'));
        expect($bruto)->not->toContain('farol')->not->toContain('leitura')->not->toContain('faixa');
    });

    it('a tela diz que farol e leitura são interpretação recalibrável e mostra a versão do pacote', function () {
        serieMes($this->empresa, '2026-10');
        config()->set('drm.conteudo_version', '3.2.1');

        faroisAbrir($this->empresa)
            ->assertSee('Faróis e leitura são interpretação e podem mudar quando as faixas forem recalibradas (pacote de conteúdo v3.2.1).')
            ->assertSee('Os valores em reais e os percentuais do mês não mudam.');
    });
});

describe('CA-4, CA-5, CA-6 — a leitura de cada situação', function () {
    it('com histórico, diz o que mudou e por quê', function () {
        serieMes($this->empresa, '2026-09', '2.900,00');
        serieMes($this->empresa, '2026-10', '3.900,00');

        faroisAbrir($this->empresa, '2026-10')
            ->assertSee('O resultado do mês ficou 50,0% abaixo do de setembro.')
            ->assertSee('O que mais explica a diferença');
    });

    it('prejuízo: direto, sem julgar, apontando a linha que mais pesou', function () {
        serieMes($this->empresa, '2026-10', '6.000,00'); // resultado −1.100

        $c = faroisAbrir($this->empresa)->assertSee('o que saiu passou do que entrou em R$ 1.100,00')
            ->assertSee('A linha que mais pesou foi “');
        expect($c->html())->not->toMatch('/culpa|péssim/iu');
    });

    it('sem faturamento: sem farol, explica o custo de manter o negócio', function () {
        $golden = json_decode((string) file_get_contents(base_path('tests/Fixtures/Drm/golden/sem_faturamento_servicos.json')), true, 512, JSON_THROW_ON_ERROR);
        DrmLancamento::factory()->create([
            'empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-10-01', 'setor' => $golden['entrada']['setor'],
            'lancamento_payload' => $golden['entrada']['payload'], 'gerado_em' => '2026-11-05 09:30:00+00',
        ]);
        foreach (['L5', 'L7', 'L11'] as $l) {
            faroisCalibrar($l);
        }
        config()->set('drm.faixas-servicos', config('drm.faixas-comercio'));

        faroisAbrir($this->empresa)
            ->assertSee('Em outubro de 2026 não houve faturamento registrado.')
            ->assertDontSeeHtml('data-farol');
    });
});
