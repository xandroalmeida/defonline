<?php

declare(strict_types=1);

use App\Actions\CalcularDiagnostico;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Support\Relatorio\PdfDiagnostico;
use Illuminate\Support\Facades\Auth;

/**
 * PDF do diagnóstico — `/diagnosticos/{diagnostico}/pdf`.
 *
 * Gerado com dompdf no próprio processo (sem Chromium/container), a partir do
 * snapshot. Os testes cobrem acesso (auth + 404 cross-tenant), o download e a
 * estrutura que impede título órfão no pé da página.
 */
function gerarDiagnosticoParaPdf(Usuario $dono): Diagnostico
{
    Auth::login($dono);
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $dono->id,
        'razao_social' => 'Indústria Teste LTDA',
        'nome_fantasia' => 'Indústria Teste',
    ]);
    $diag = app(CalcularDiagnostico::class)->execute($empresa, [
        'Q01' => 1, 'Q02' => '50000', 'Q03' => '20000', 'Q04' => '10000',
        'Q05' => '300000', 'Q06' => '40000', 'Q07' => '80000', 'Q08' => '50000',
        'Q09' => '100000', 'Q10' => 90, 'Q11' => 15, 'Q12' => 15, 'Q13' => '2',
        'Q14' => '20000', 'Q15' => '8000', 'Q16' => '2000',
    ]);
    Auth::logout();

    return $diag;
}

it('exige autenticação', function () {
    $diag = gerarDiagnosticoParaPdf(Usuario::factory()->create());

    $this->get("/diagnosticos/{$diag->id}/pdf")->assertRedirect('/login');
});

it('devolve 404 silente para diagnóstico de outro usuário (IDR-009)', function () {
    $diag = gerarDiagnosticoParaPdf(Usuario::factory()->create());

    $this->actingAs(Usuario::factory()->create())
        ->get("/diagnosticos/{$diag->id}/pdf")
        ->assertNotFound();
});

it('baixa o PDF do próprio diagnóstico como anexo', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnosticoParaPdf($dono);

    $resposta = $this->actingAs($dono)->get("/diagnosticos/{$diag->id}/pdf");

    $resposta->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader(
            'Content-Disposition',
            'attachment; filename="diagnostico-industria-teste-'.$diag->gerado_em->format('Y-m-d').'.pdf"',
        );
    expect($resposta->getContent())->toStartWith('%PDF-');
});

it('a tela do diagnóstico oferece o botão Baixar PDF', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnosticoParaPdf($dono);

    $this->actingAs($dono)->get("/diagnosticos/{$diag->id}")
        ->assertOk()
        ->assertSee('Baixar PDF')
        ->assertSee(route('diagnosticos.pdf', $diag), escape: false);
});

it('monta capa, resumo, indicadores e glossário a partir do snapshot', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnosticoParaPdf($dono);
    Auth::login($dono);

    $html = PdfDiagnostico::padrao()->html($diag->fresh());

    expect($html)
        ->toContain('class="capa"')
        ->toContain('Diagnóstico<br>Econômico-Financeiro')
        ->toContain('Indústria Teste')
        ->toContain('Resumo executivo')
        ->toContain('Indicadores essenciais')
        ->toContain('Buscar manter a margem atual.')
        ->toContain('Glossário')
        ->toContain('Balanço Adaptado / DRE Adaptada')
        ->toContain('motor v'.$diag->motor_version);
});

it('o CSS do PDF sai com os tokens resolvidos (dompdf não entende var nem color-mix)', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnosticoParaPdf($dono);
    Auth::login($dono);

    $html = PdfDiagnostico::padrao()->html($diag->fresh());

    expect($html)
        ->not->toContain('var(--')
        ->not->toContain('color-mix(');
});

it('nenhum título de seção fica solto: cada um está preso ao seu primeiro conteúdo', function () {
    $dono = Usuario::factory()->create();
    $diag = gerarDiagnosticoParaPdf($dono);
    Auth::login($dono);

    $dom = new DOMDocument;
    @$dom->loadHTML(PdfDiagnostico::padrao()->html($diag->fresh()));
    $xpath = new DOMXPath($dom);

    $titulos = $xpath->query('//h2[contains(@class, "secao__titulo")]');
    expect($titulos->length)->toBeGreaterThanOrEqual(4);

    foreach ($titulos as $h2) {
        $pai = $h2->parentNode;
        $classes = $pai instanceof DOMElement ? $pai->getAttribute('class') : '';

        // Ou o título mora num bloco indivisível junto do conteúdo, ou num bloco
        // com page-break-after: avoid imediatamente antes da tabela (cujo thead repete).
        $preso = str_contains($classes, 'manter-junto') || str_contains($classes, 'titulo-da-tabela');
        expect($preso)->toBeTrue("Título \"{$h2->textContent}\" sem amarração de quebra de página");

        // E nunca é o último filho do bloco — há conteúdo depois dele no mesmo bloco/seção.
        $irmao = $h2->nextSibling;
        while ($irmao && ! $irmao instanceof DOMElement) {
            $irmao = $irmao->nextSibling;
        }
        expect($irmao)->not->toBeNull();
    }

    // O título da tabela vem imediatamente antes da tabela.
    $tituloTabela = $xpath->query('//div[contains(@class, "titulo-da-tabela")]')->item(0);
    $proximo = $tituloTabela->nextSibling;
    while ($proximo && ! $proximo instanceof DOMElement) {
        $proximo = $proximo->nextSibling;
    }
    expect($proximo->nodeName)->toBe('table')
        ->and($xpath->query('//table[contains(@class, "indicadores")]/thead')->length)->toBe(1);
});

it('a regra de quebra está no pdf.css', function () {
    $css = file_get_contents(resource_path('css/pdf.css'));

    expect($css)
        ->toMatch('/\.manter-junto,\s*\.indivisivel\s*\{\s*page-break-inside:\s*avoid;/')
        ->toMatch('/\.titulo-da-tabela\s*\{\s*page-break-after:\s*avoid;/')
        ->toMatch('/\.indicadores tbody tr\s*\{\s*page-break-inside:\s*avoid;/')
        ->toMatch('/\.verbete\s*\{[^}]*page-break-inside:\s*avoid;/');
});
