---
pdr_id: PDR-004
slug: decisoes-estruturais-drm
title: Resolução das decisões estruturais e editoriais do DRM — pró-labore, percentuais, rubricas sazonais e linhas não operacionais
status: accepted
decided_at: 2026-08-04
decided_by: PO (Alexandro / Claude) — respostas de negócio da EBC
supersedes: null
superseded_by: null
related_epics: [EPIC-010]
related_pdrs: [PDR-003]
resolve: ["[DECIDIR-B]", "[DECIDIR-C]", "[DECIDIR-D]", "[DECIDIR-E]"]
---

# PDR-004 — Decisões estruturais e editoriais do DRM

## Contexto

O `PDR-003` criou o módulo DRM e deixou cinco decisões em aberto, quatro delas dependentes de resposta de negócio. Duas — `[DECIDIR-B]` e `[DECIDIR-E]` — bloqueavam a construção do motor. As respostas chegaram em 04/08/2026 e são registradas aqui.

Três das quatro são baratas e fecham o assunto. A quarta, `[DECIDIR-D]`, foi respondida com uma escolha que **não cabe no módulo como ele está desenhado** — e este PDR trata isso explicitamente em vez de fingir que é um parâmetro.

`[DECIDIR-A]` (nome "Raio X do Negócio") permanece aberto e não bloqueia nada.

---

## Decisão B — Posição do pró-labore: **parametrizável pelo cliente**

> **Resposta:** parametrizável. **Default:** pró-labore fora do Resultado Operacional, como o Key User desenhou.

### O que significa

A demonstração passa a ter **dois modos de apresentação**, escolhidos por Empresa Analisada e alternáveis a qualquer momento:

- **Modo "fora" (default).** O pró-labore é linha própria, deduzida no fim. O dono vê "quanto sobrou depois de eu me pagar" como informação destacada. A linha de Resultado Operacional **não se chama EBITDA** neste modo.
- **Modo "dentro".** O pró-labore compõe as despesas administrativas. O Resultado Operacional é o EBITDA na definição padrão, coerente com o relatório de Diagnóstico.

### Por que isso é barato — e por que era o risco que eu mais temia

A preocupação original (F2 do parecer) era que a mesma empresa visse duas Margens EBITDA conflitantes entre DRM e Diagnóstico. A parametrização **resolve** esse problema em vez de agravá-lo: o cliente que quiser reconciliar os dois módulos liga o modo "dentro" e os números passam a conversar.

E é barato por uma razão aritmética verificada: **o Lucro Líquido do Mês é idêntico nos dois modos.** Só as linhas intermediárias mudam. Conferido em 2000 casos aleatórios — a resposta à pergunta que dá nome ao módulo, "meu negócio deu lucro?", nunca muda com o parâmetro.

Como a `STORY-048` já exigia calcular e persistir os dois valores no snapshot (CA-6, o "EBITDA padrão paralelo"), o parâmetro é **puramente de apresentação**:

- Alternar não recalcula nada.
- Alternar não quebra a série histórica — meses antigos são reexibidos no modo novo, porque ambos os valores estão em todos os snapshots.
- Não há ambiguidade sobre "em que modo este mês foi lançado", porque o modo não afeta o lançamento.

Esta última consequência é a mais importante. Se o parâmetro afetasse o cálculo, uma empresa que o alternasse em julho teria uma série incomparável — seis meses medidos de um jeito, seis de outro. Não é o caso.

### Consequências

- Rótulos, número de linhas e ordem passam a depender do modo. Especificado em `epics/EPIC-010-*/design/estrutura-drm.md` §1.
- O modo vigente é exibido junto à demonstração, para que o usuário saiba qual leitura está vendo.
- A `STORY-048` deixa de estar bloqueada.

---

## Decisão C — Percentuais e rótulos: **sim**

> **Resposta:** sim para ambos os itens.

1. **Percentual em todas as linhas**, incluindo Receita Bruta (ancorada em 100%), Deduções e Receita Operacional Líquida — que a planilha original não trazia. Fecha a inconsistência F3. A carga tributária sobre a venda passa a ser leitura direta, e é uma das que o público-alvo mais procura.
2. **Rótulo "antes do IR/CSLL" substituído por "antes do pró-labore"** (F10). O optante do Simples não recolhe IR e CSLL separadamente — ambos já estão na guia única lançada em `D02` —, e a estrutura corretamente não tem linha de IR/CSLL depois. O rótulo anunciava uma etapa inexistente.

Esta linha só existe no modo "fora"; no modo "dentro" ela colapsa no Lucro Líquido.

---

## Decisão E — Linhas não operacionais: **sim**

> **Resposta:** sim.

Entram as linhas de **Outras receitas** e **Outras despesas** (campos `D41` e `D42`), posicionadas **após** o Resultado Operacional para não contaminar as margens operacionais. Ficam ocultas quando zeradas.

Resolve o problema levantado em F7: venda de equipamento usado, rendimento de aplicação, indenização recebida, multa contratual paga. Sem essas linhas, o usuário forçaria esses eventos em alguma rubrica errada, contaminando a série histórica.

A `STORY-048` deixa de estar bloqueada também por este item.

---

## Decisão D — Férias e 13º: **provisão contábil completa**

> **Resposta:** parametrizável pelo cliente, na modalidade de provisão contábil completa — o sistema acumula provisão mês a mês e baixa contra o pagamento quando ele ocorre.

### A decisão é aceita. O caminho não é o de um parâmetro.

Registro isto com clareza porque é a diferença entre uma estória e um módulo.

Provisão contábil não é uma escolha de exibição, como a decisão B. É uma **conta que atravessa meses**: o sistema acumula 1/12 por mês, guarda esse acúmulo em algum lugar, e o abate quando o pagamento acontece. Se o pagamento for maior que o provisionado, sobra diferença; se for menor, sobra saldo. Esse saldo é um **passivo** — dinheiro que a empresa deve ao funcionário e ainda não pagou.

E aqui está o conflito: **o DRM não tem onde guardar um passivo.** O módulo foi desenhado, no `PDR-003`, como demonstração de resultado em regime de caixa puro, sem qualquer conta patrimonial. É exatamente essa ausência que o torna acessível ao MEI — quem tem contas patrimoniais tem escrita contábil, e quem tem escrita contábil não é o público deste módulo.

Provisionar corretamente exigiria ainda: definir a base de cálculo (o 13º é um salário; férias são um salário mais um terço), provisionar os encargos sobre eles, tratar funcionário que entra ou sai no meio do ano, e decidir o que fazer com saldo residual na virada do exercício. É contabilidade de folha, não um campo a mais no formulário.

### Encaminhamento

A decisão vale como **alvo**. O caminho recebe o mesmo tratamento que dei à retificação em F12: **spike de arquitetura antes de qualquer implementação** — `STORY-058`.

O spike responde à pergunta central — *onde mora o saldo de provisão num módulo que só tem resultado?* — e tem, explicitamente, uma saída legítima: concluir que a provisão completa não cabe no EPIC-010 e deve virar épico próprio na onda seguinte. Mesmo padrão da `STORY-053`, que pode concluir que o PDF não cabe.

**Enquanto o spike não conclui, o MVP entrega a intenção por trás do pedido.** O que motivou a pergunta é concreto: dezembro não pode parecer um desastre só porque o 13º foi pago. A `STORY-051` já resolve isso sem nenhuma conta patrimonial — o alerta de rubrica atípica detecta o mês, a série o marca visualmente, e o comparativo o explica em vez de deixar o usuário concluir a coisa errada.

Não é a provisão contábil. Mas responde à dor no prazo do épico, e não hipoteca a decisão maior.

---

## Consequências consolidadas

### Positivas

- O motor está desbloqueado. `[DECIDIR-B]` e `[DECIDIR-E]` eram os dois únicos itens que travavam a construção.
- A parametrização do pró-labore transforma o principal risco conceitual do módulo — divergência de EBITDA entre DRM e Diagnóstico — em recurso do produto.
- A demonstração fica mais completa e mais honesta que a planilha original em três pontos (percentuais, rótulo do resultado, linhas não operacionais).

### Negativas / trade-offs aceitos

- **Dois modos de apresentação dobram a superfície de teste** da tela e da exportação. Aceito: o custo é de teste, não de modelagem, porque o parâmetro não afeta o cálculo.
- **A provisão contábil fica sem data.** O spike pode concluir que não cabe nesta onda. É o trade-off de aceitar uma decisão correta cujo custo real só aparece quando se olha de perto.
- Mais uma preferência por empresa para gerenciar e explicar.

### Para o time técnico

- `STORY-048` sai de `blocked`; ganha os dois modos e as linhas não operacionais.
- `STORY-049` e `STORY-055` passam a cobrir os dois modos.
- `STORY-051` entrega o tratamento provisório de rubricas sazonais.
- `STORY-052` continua bloqueada, agora **só** pelas faixas de farol por setor — que seguem exigindo validação externa da EBC, no mesmo rigor aplicado ao Diagnóstico.
- `STORY-058` (spike) criada.

## Decisão que permanece aberta

`[DECIDIR-A]` — "Raio X do Negócio" nomeia o módulo DRM, o relatório de Diagnóstico ou a plataforma inteira. Não bloqueia nada; pode entrar até o fim do épico.

## Sinais de revisão

- **Se menos de 5% das empresas alternarem o modo do pró-labore** em seis meses, o parâmetro não estava respondendo a uma necessidade real — e vale simplificar para um modo só, reduzindo superfície de teste.
- **Se o spike da `STORY-058` concluir que a provisão exige contas patrimoniais no DRM**, o `PDR-003` precisa ser reaberto: o módulo deixaria de ser "resultado em caixa puro", que é a premissa que o torna acessível ao MEI.
- **Se o tratamento provisório de rubricas sazonais da `STORY-051` bastar** na prática — usuários deixam de pedir provisão depois de ver o mês marcado como atípico —, a provisão completa pode nunca precisar ser construída. Vale medir antes de investir.
