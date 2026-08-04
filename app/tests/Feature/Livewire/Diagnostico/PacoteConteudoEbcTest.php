<?php

declare(strict_types=1);

use App\Livewire\Diagnostico\Quiz;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * STORY-042 — pacote de conteúdo EBC, parte do quiz (CA-1 a CA-6).
 *
 * Devolutiva do Edvan (`.docx` "DEF on line - MVP 270526 - observações ebc").
 * São mudanças **editoriais**: nenhuma regra do motor muda aqui. Os textos são
 * transcritos literalmente do `.docx` (decisão do PO no briefing de abertura),
 * por isso as asserções usam **igualdade estrita da string inteira** e não
 * `assertSee` de trechos: `assertSee` de um prefixo continuaria passando se o
 * texto antigo voltasse com o novo concatenado.
 *
 * Nota de rótulo: o CA-6 fala em "textos auxiliares de Q17", mas as strings
 * vivem no campo **Q20**, renderizado dentro do sub-bloco gated por
 * `Q17 === 'sim'`. Ver "Notas do agente" da estória.
 */
beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()
        ->state(['usuario_id' => $this->usuario->id])
        ->create();
});

/** Bloco 1 → 2, sem preencher nada (o Bloco 1 só tem o Q01 readonly). */
function ebcQuizNoBloco2(TestCase $test): Testable
{
    return Livewire::test(Quiz::class, ['empresa' => $test->empresa])
        ->call('proximoBloco');
}

/** Bloco 2 preenchido com valores saudáveis → para no Bloco 3. */
function ebcQuizNoBloco3(TestCase $test): Testable
{
    return ebcQuizNoBloco2($test)
        ->set('Q08', '50000')
        ->set('Q09', '100000')
        ->set('Q14', '20000')
        ->set('Q15', '8000')
        ->set('Q16', '2000')
        ->set('Q10', '90')
        ->set('Q11', '15')
        ->set('Q12', '15')
        ->set('Q13', '2')
        ->call('proximoBloco');
}

/**
 * Bloco 4 com o sub-bloco de captação **aberto** (`Q17 = sim`).
 *
 * O `Q17 = sim` não é detalhe: com "não", os campos Q18–Q20 nem entram no DOM
 * e um `assertDontSee` sobre os textos auxiliares passaria sem provar nada.
 */
function ebcQuizNoBloco4ComCaptacao(TestCase $test): Testable
{
    return ebcQuizNoBloco3($test)
        ->set('Q02', '50000')
        ->set('Q03', '20000')
        ->set('Q04', '10000')
        ->set('Q05', '300000')
        ->set('Q06', '40000')
        ->set('Q07', '80000')
        ->call('proximoBloco')
        ->set('Q17', 'sim');
}

// ============================================================================
// CA-1 — Bloco 2 não fala mais em anualização
// ============================================================================

it('CA-1 — Bloco 2 não exibe o texto da anualização (× 12)', function () {
    ebcQuizNoBloco2($this)
        ->assertDontSee('anualiza')
        ->assertDontSee('× 12')
        ->assertDontSee('×12');
});

it('CA-1 (borda) — a remoção foi cirúrgica: o Bloco 2 mantém "Médias mensais dos últimos 12 meses"', function () {
    // Se alguém apagar o parágrafo inteiro em vez da oração final, o CA-1 passa
    // e o usuário perde a informação de que os valores são médias mensais.
    ebcQuizNoBloco2($this)->assertSee('Médias mensais dos últimos 12 meses');
});

it('CA-1 (borda) — nenhum outro bloco do quiz reintroduz o texto da anualização', function () {
    ebcQuizNoBloco3($this)->assertDontSee('anualiza');
    ebcQuizNoBloco4ComCaptacao($this)->assertDontSee('anualiza');
});

// ============================================================================
// CA-2 / CA-3 — subtítulos dos itens 3 e 4 do Bloco 2
// ============================================================================

it('CA-2 — subtítulo de "Custos e despesas fixas" traz a lista de exemplos do .docx', function () {
    ebcQuizNoBloco2($this)->assertSee(
        'Folha de Pagamento, Encargos sociais, contador, aluguel, condomínio, energia, água, telefone, internet, franquia, retirada de sócios, etc.',
    );
});

it('CA-2 (borda) — o subtítulo antigo do item 3 sumiu por inteiro', function () {
    // `assertSee` do texto novo sozinho não prova a substituição: passaria com os
    // dois parágrafos coexistindo.
    ebcQuizNoBloco2($this)->assertDontSee('Folha, contador, aluguel, condomínio, energia, água, telefone, internet, retirada de sócios.');
});

it('CA-3 — subtítulo de "Custos e despesas variáveis" traz a lista de exemplos do .docx', function () {
    ebcQuizNoBloco2($this)->assertSee(
        'Fretes, comissões, tributos, despesas de vendas, outras despesas variáveis, etc.',
    );
});

it('CA-3 (borda) — o subtítulo antigo do item 4 sumiu por inteiro', function () {
    ebcQuizNoBloco2($this)->assertDontSee('Fretes, comissões, tributos, despesas variáveis.');
});

// ============================================================================
// CA-4 — título do Bloco 3
// ============================================================================

it('CA-4 — o cabeçalho do Bloco 3 exibe o título novo', function () {
    ebcQuizNoBloco3($this)->assertSee('Bloco 3 de 4 — Saldos Atuais – uma foto da situação econômica do negócio hoje');
});

it('CA-4 (borda) — o título antigo "Balanço" saiu do cabeçalho do Bloco 3', function () {
    // Pega o caso de colocarem o texto novo em outro lugar e esquecerem o título.
    ebcQuizNoBloco3($this)->assertDontSee('Bloco 3 de 4 — Balanço');
});

it('CA-4 (borda) — o default vencido do título do Bloco 2 foi aplicado: segue "DRE e Operação"', function () {
    // Pendência ao Edvan sem resposta até 2026-06-04 → manter o título atual.
    // Sem este teste, "não mexer" é indistinguível de "esqueci de mexer".
    ebcQuizNoBloco2($this)->assertSee('Bloco 2 de 4 — DRE e Operação');
});

// ============================================================================
// CA-5 — Patrimônio (imobilizado)
// ============================================================================

it('CA-5 — subtítulo do Patrimônio (imobilizado) exibe o texto novo', function () {
    ebcQuizNoBloco3($this)->assertSee('Imóveis, equipamentos e veículos livres de ônus – valor estimado para venda rápida');
});

it('CA-5 (borda) — o subtítulo antigo do imobilizado sumiu por inteiro', function () {
    ebcQuizNoBloco3($this)->assertDontSee('Imóveis, equipamentos, veículos — valor de venda rápida.');
});

// ============================================================================
// CA-6 — os 2 textos auxiliares do sub-bloco de captação (campo Q20)
// ============================================================================

it('CA-6 — os 2 textos auxiliares do Q20 não aparecem com o sub-bloco de captação aberto', function () {
    ebcQuizNoBloco4ComCaptacao($this)
        ->assertSee('Valor que precisa captar')            // sanidade: o sub-bloco está mesmo renderizado
        ->assertSee('Venda mensal com cartão / duplicatas') // sanidade: o Q20 está mesmo na tela
        ->assertDontSee('Base de cálculo da capacidade de garantia (3× esse valor)')
        ->assertDontSee('Deve ser inferior à venda mensal total (Q09)');
});

it('CA-6 (borda) — o tooltip do Q20 é outra superfície e continua intacto', function () {
    // A estória manda ocultar o **texto auxiliar**; o box explicativo (<x-help>,
    // STORY-033) não está no escopo. Este teste trava o limite: se alguém
    // "limpar" o tooltip junto, o CA-6 continuaria verde e a perda passaria.
    $tooltip = (string) config('quiz.help-industria.campos.Q20');

    expect($tooltip)->toContain('capacidade de garantia');

    ebcQuizNoBloco4ComCaptacao($this)->assertSee('limite aproximado de 3× esse valor');
});
