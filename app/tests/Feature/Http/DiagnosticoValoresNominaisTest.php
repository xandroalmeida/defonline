<?php

declare(strict_types=1);

use App\Actions\CalcularDiagnostico;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Support\Relatorio\ValoresNominais;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * STORY-044 — valor nominal junto a cada indicador do relatório.
 *
 * Cobre o que só aparece no HTML renderizado:
 *   - CA-1 (os termos de cada indicador composto, na tabela e nos cards)
 *   - CA-3 (indicador em dias/percentual sem origem monetária não exibe nada —
 *           sem placeholder, sem "N/A")
 *   - CA-4 (indisponível mantém a mensagem semântica, sem nominal)
 *   - CA-5 (hierarquia: nominal em token secundário/xs; indicador dominante)
 *   - CA-8 (snapshot antigo sem os dados no payload não quebra a tela)
 *   - CA-9 (nominal associado ao indicador na mesma linha/card)
 *   - CA-10 (o GET do relatório não altera o snapshot persistido)
 *
 * A regra de composição em si (quais termos, quais faltantes derrubam quais
 * indicadores) é testada em `tests/Unit/Support/Relatorio/ValoresNominaisTest.php`.
 *
 * Payload usado (mensal): Q08 50k compras · Q09 100k vendas · Q14 20k fixas ·
 * Q15 8k variáveis · Q16 2k financeiras · Q02 50k · Q03 80k · Q04 60k ·
 * Q05 300k · Q06 40k · Q07 30k.
 * Anualizado: Vendas 1.200.000 · LB 600.000 · EBITDA 264.000 · LOL 240.000 ·
 * AT 490.000 · PC 70.000 · PL 420.000 · NCG 110.000 · Dív. líquida −10.000.
 */
function payloadNominalRelatorio(): array
{
    return [
        'Q01' => 1,
        'Q02' => '50000.00', 'Q03' => '80000.00', 'Q04' => '60000.00',
        'Q05' => '300000.00', 'Q06' => '40000.00', 'Q07' => '30000.00',
        'Q08' => '50000.00', 'Q09' => '100000.00',
        'Q10' => 45, 'Q11' => 30, 'Q12' => 45, 'Q13' => '2.00',
        'Q14' => '20000.00', 'Q15' => '8000.00', 'Q16' => '2000.00',
    ];
}

function diagnosticoNominal(Usuario $dono, ?array $payload = null): Diagnostico
{
    Auth::login($dono);
    $empresa = EmpresaAnalisada::factory()->create([
        'usuario_id' => $dono->id,
        'razao_social' => 'Metalúrgica Nominal LTDA',
        'nome_fantasia' => 'Metalúrgica Nominal',
    ]);
    $diag = app(CalcularDiagnostico::class)->execute($empresa, $payload ?? payloadNominalRelatorio());
    Auth::logout();

    return $diag;
}

function htmlRelatorioNominal(Usuario $dono, Diagnostico $diag): string
{
    return (string) test()->actingAs($dono)->get("/diagnosticos/{$diag->id}")->assertOk()->getContent();
}

// ---------------------------------------------------------------------------
// CA-1 — cobertura dos indicadores
// ---------------------------------------------------------------------------

it('exibe os dois termos nominais da Margem Bruta junto ao indicador (CA-1)', function () {
    $dono = Usuario::factory()->create();
    $html = htmlRelatorioNominal($dono, diagnosticoNominal($dono));

    expect($html)
        ->toContain('data-testid="nominais-margem_bruta"')
        ->toContain('Lucro bruto (12 meses)')
        ->toContain('R$ 600.000,00')
        ->toContain('Vendas (12 meses)')
        ->toContain('R$ 1.200.000,00');
});

it('exibe bloco nominal para os 9 indicadores compostos, na tabela e nos cards (CA-1, CA-7)', function () {
    $dono = Usuario::factory()->create();
    $html = htmlRelatorioNominal($dono, diagnosticoNominal($dono));

    foreach (ValoresNominais::CODIGOS_COM_NOMINAL as $codigo) {
        // Os 8 com farol saem duas vezes — os dois layouts coexistem no DOM
        // (tabela em md+, cards em <md) e o bloco tem que existir nos dois,
        // senão o mobile fica sem a informação. O NCG absoluto é informativo:
        // sai uma vez só, no card próprio.
        $esperado = $codigo === 'ncg_absoluto' ? 1 : 2;
        expect(substr_count($html, 'data-testid="nominais-'.$codigo.'"'))
            ->toBe($esperado, "Ocorrências inesperadas do bloco nominal de {$codigo}");
    }
});

it('mostra os dois termos de uma razão de balanço, não só o resultado (CA-1)', function () {
    $dono = Usuario::factory()->create();
    $html = htmlRelatorioNominal($dono, diagnosticoNominal($dono));

    expect($html)
        ->toContain('Passivo circulante')
        ->toContain('R$ 70.000,00')
        ->toContain('Patrimônio líquido')
        ->toContain('R$ 420.000,00');
});

it('exibe os três componentes do NCG absoluto no card informativo (CA-1)', function () {
    $dono = Usuario::factory()->create();
    $html = htmlRelatorioNominal($dono, diagnosticoNominal($dono));

    expect($html)
        ->toContain('data-testid="nominais-ncg_absoluto"')
        ->toContain('Contas a receber')
        ->toContain('Estoque')
        ->toContain('Fornecedores a pagar');
});

it('formata dívida líquida negativa com sinal no relatório (CA-6)', function () {
    $dono = Usuario::factory()->create();
    $html = htmlRelatorioNominal($dono, diagnosticoNominal($dono));

    expect($html)->toContain('-R$ 10.000,00');
});

// ---------------------------------------------------------------------------
// CA-3 — sem valor nominal aplicável → nada é exibido
// ---------------------------------------------------------------------------

it('não exibe bloco nominal para prazos e inadimplência, nem placeholder vazio (CA-3)', function () {
    $dono = Usuario::factory()->create();
    $html = htmlRelatorioNominal($dono, diagnosticoNominal($dono));

    foreach (['pmc', 'pme', 'pmr', 'ciclo_financeiro', 'inadimplencia'] as $codigo) {
        expect($html)->not->toContain('data-testid="nominais-'.$codigo.'"');
    }

    // Sem "N/A" e sem travessão solto como marcador de nominal ausente.
    expect($html)->not->toContain('N/A');
});

// ---------------------------------------------------------------------------
// CA-4 — indicador indisponível
// ---------------------------------------------------------------------------

it('indicador indisponível mantém a mensagem semântica e não ganha nominal (CA-4)', function () {
    $dono = Usuario::factory()->create();
    // Sem Q14/Q15 o EBITDA é nulo → margem EBITDA e dívida líq./EBITDA indisponíveis,
    // mas o payload ainda tem vendas e compras (a margem bruta continua calculável).
    $payload = payloadNominalRelatorio();
    $payload['Q14'] = null;
    $payload['Q15'] = null;
    $diag = diagnosticoNominal($dono, $payload);

    $snapshot = (array) $diag->indicadores_calculados;
    expect($snapshot['margem_ebitda']['valor'])->toBeNull();
    expect($snapshot['margem_bruta']['valor'])->not->toBeNull();

    $html = htmlRelatorioNominal($dono, $diag);

    expect($html)
        ->toContain('Indisponível')
        ->toContain($snapshot['margem_ebitda']['mensagem'])
        ->not->toContain('data-testid="nominais-margem_ebitda"');

    // O indicador calculável ao lado continua com o nominal dele.
    expect($html)->toContain('data-testid="nominais-margem_bruta"');
});

// ---------------------------------------------------------------------------
// CA-5 / CA-9 — hierarquia visual e associação com o indicador
// ---------------------------------------------------------------------------

it('renderiza o nominal dentro da mesma linha do indicador, após o valor (CA-5, CA-9)', function () {
    $dono = Usuario::factory()->create();
    $html = htmlRelatorioNominal($dono, diagnosticoNominal($dono));

    // A linha da tabela é a unidade de associação para leitor de tela: o
    // <th scope="row"> nomeia o indicador e o nominal vive na mesma <tr>.
    $inicioLinha = mb_strpos($html, 'data-codigo="margem_bruta"');
    $fimLinha = mb_strpos($html, '</tr>', $inicioLinha);
    $linha = mb_substr($html, $inicioLinha, $fimLinha - $inicioLinha);

    expect($linha)->toContain('data-testid="nominais-margem_bruta"');
    // Valor do indicador vem ANTES do bloco de apoio.
    expect(mb_strpos($linha, '50,0%'))->toBeLessThan(mb_strpos($linha, 'Lucro bruto (12 meses)'));
    // Apoio usa o token secundário e escala reduzida (IDR-008) — não compete.
    expect($linha)->toContain('text-[color:var(--color-secondary)] text-xs');
});

// ---------------------------------------------------------------------------
// CA-8 — compatibilidade retroativa
// ---------------------------------------------------------------------------

it('snapshot antigo sem os campos no quiz_payload renderiza sem quebrar e sem nominal (CA-8)', function () {
    $dono = Usuario::factory()->create();
    Auth::login($dono);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);
    // Registro de motor antigo: indicadores calculados presentes, payload truncado
    // (formato que não guardava os campos monetários com estas chaves).
    $diag = Diagnostico::factory()->paraEmpresa($empresa)->create([
        'motor_version' => '1.0.0',
        'quiz_payload' => ['Q01' => 1],
    ]);
    Auth::logout();

    $html = htmlRelatorioNominal($dono, $diag);

    expect($html)->toContain('Margem Bruta');
    foreach (ValoresNominais::CODIGOS_COM_NOMINAL as $codigo) {
        expect($html)->not->toContain('data-testid="nominais-'.$codigo.'"');
    }
});

it('snapshot antigo com payload parcial exibe só os indicadores derriváveis (CA-8)', function () {
    $dono = Usuario::factory()->create();
    Auth::login($dono);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);
    // Só o balanço cíclico chegou: NCG absoluto é derivável; as margens não.
    $diag = Diagnostico::factory()->paraEmpresa($empresa)->create([
        'quiz_payload' => ['Q01' => 1, 'Q03' => '80000.00', 'Q04' => '60000.00', 'Q07' => '30000.00'],
    ]);
    Auth::logout();

    $html = htmlRelatorioNominal($dono, $diag);

    expect($html)
        ->toContain('data-testid="nominais-ncg_absoluto"')
        ->not->toContain('data-testid="nominais-margem_bruta"');
});

// ---------------------------------------------------------------------------
// CA-2 / CA-10 — a restrição dura: apresentação não toca o snapshot
// ---------------------------------------------------------------------------

it('renderizar o relatório não altera payload_hash, versões nem indicadores (CA-2, CA-10)', function () {
    $dono = Usuario::factory()->create();
    $diag = diagnosticoNominal($dono);

    // Comparação linha crua contra linha crua, direto na tabela: é a evidência
    // do CA-2. Não passa por Eloquent nem por cast — o Postgres reordena as
    // chaves de `jsonb` na gravação, então o objeto em memória e o relido do
    // banco diferem na ORDEM (não no conteúdo), e comparar via model daria um
    // falso positivo de alteração.
    $linhaCrua = fn (): array => (array) DB::table('diagnosticos')->where('id', $diag->id)->first();

    $antes = $linhaCrua();

    htmlRelatorioNominal($dono, $diag);

    expect($linhaCrua())->toBe($antes);

    // Redundância proposital: nomeia as colunas que a estória proíbe tocar, para
    // que a falha aponte o CA em vez de "a linha mudou".
    $depois = $linhaCrua();
    foreach (['payload_hash', 'motor_version', 'matrix_version', 'quiz_payload', 'indicadores_calculados', 'resumo_executivo', 'updated_at'] as $coluna) {
        expect($depois[$coluna])->toBe($antes[$coluna], "Coluna {$coluna} alterada pela renderização do relatório");
    }
});

it('a versão do motor exibida continua sendo a do snapshot, não a do config (CA-2)', function () {
    $dono = Usuario::factory()->create();
    Auth::login($dono);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);
    $diag = Diagnostico::factory()->paraEmpresa($empresa)->create([
        'motor_version' => '0.9.0',
        'matrix_version' => 'jul-2025',
    ]);
    Auth::logout();

    $html = htmlRelatorioNominal($dono, $diag);

    expect($html)
        ->toContain('motor v0.9.0')
        ->not->toContain('motor v'.config('motor.version'));
});
