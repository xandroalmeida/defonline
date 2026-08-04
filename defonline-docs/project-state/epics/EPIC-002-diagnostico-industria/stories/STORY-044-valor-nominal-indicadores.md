---
story_id: STORY-044
slug: valor-nominal-indicadores
title: "Valor nominal junto a cada indicador do relatório de Diagnóstico"
epic_id: EPIC-002
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: in_review
owner_agent: programador (claude-opus-5)
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: S
origem: "Feedback do Key User José Marques, 03/08/2026 — item 1"
---

# STORY-044 — Valor nominal junto a cada indicador

## Contexto

O Key User José Marques pediu, no feedback de 03/08/2026: *"registrar no relatório também o valor nominal junto a cada indicador apurado"*.

O relatório atual mostra o indicador calculado — margem em percentual, prazo em dias, ciclo em dias — mas não mostra os números que o produziram. Quem lê "margem bruta de 22%" não sabe se isso são R$ 22 mil sobre R$ 100 mil ou R$ 220 mil sobre R$ 1 milhão. A ordem de grandeza muda completamente a leitura, e é o primeiro reflexo de quem confere um número: "22% de quanto?".

Há um efeito de confiança além da leitura. Indicador sem valor nominal parece caixa-preta; com o valor ao lado, o usuário confere e passa a confiar no resto do relatório.

**Este item não faz parte do módulo DRM.** É melhoria do relatório de Diagnóstico já entregue — pós-devolutiva, no mesmo padrão das STORY-038 a STORY-043.

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- Ler antes: `revisao/analise-po-feedback-jose-marques-2026ago03.md` §1, item A; o relatório de Diagnóstico e o formatador de indicadores já em produção.

## O quê

Exibir, junto a cada indicador do relatório de Diagnóstico, os valores nominais que o originaram, em reais e no padrão de formatação BR.

## Por quê

Reduz a percepção de caixa-preta no relatório e responde ao pedido direto do Key User. Custo baixo, ganho de confiança desproporcional.

## Critérios de aceite

- [x] **CA-1 (Cobertura dos indicadores):** cada indicador do relatório exibe o valor ou os valores nominais que o compõem. Onde o indicador é uma razão, os dois termos aparecem — por exemplo, uma margem mostra o resultado e a base de receita sobre a qual foi calculada.
- [x] **CA-2 (Fonte é o snapshot):** os valores nominais derivam do snapshot já persistido do diagnóstico. **Nenhum recálculo, nenhuma nova invocação do motor, nenhuma alteração no `payload_hash` nem na versão do motor** (IDR-010).
- [x] **CA-3 (Indicadores em dias e percentual):** indicadores expressos em dias ou percentual exibem os valores monetários de origem quando existirem. Quando não houver valor nominal aplicável, nada é exibido — sem placeholder vazio, sem "N/A".
- [x] **CA-4 (Indicador indisponível):** indicador marcado como indisponível continua exibindo sua mensagem semântica atual, sem valor nominal.
- [x] **CA-5 (Hierarquia visual):** o valor nominal é informação de apoio, não concorre com o indicador. O indicador permanece o elemento dominante da linha ou do card.
- [x] **CA-6 (Formatação BR):** padrão monetário brasileiro, consistente com o resto do produto.
- [x] **CA-7 (Mobile e desktop):** funciona na tabela densa de desktop e nos cards de mobile, sem scroll horizontal em 360×800.
- [x] **CA-8 (Compatibilidade retroativa):** diagnósticos gerados por versões anteriores do motor continuam renderizando. Se o snapshot antigo não tiver algum dado necessário, o valor nominal daquele indicador simplesmente não aparece — a tela **não quebra** e não exibe erro.
- [x] **CA-9 (Acessibilidade):** o valor nominal é associado ao indicador de forma legível por leitor de tela, sem duplicar leitura confusa.
- [x] **CA-10 (Testes):** feature cobrindo render com valores nominais, indicador indisponível, e snapshot de versão antiga sem os dados; verificação de que o `payload_hash` permanece inalterado.
- [x] **CA-11 (Cobertura):** ≥ 80% no código novo.

## Fora de escopo

- Qualquer coisa do módulo DRM — é o EPIC-010.
- Alterar fórmulas, faixas de farol, ordem dos indicadores ou textos de recomendação.
- Alterar a versão do motor. Esta estória é de apresentação, não de cálculo.
- Exportação em PDF (EPIC-007).

## Padrões de qualidade exigidos

`defonline-docs/skills/po/references/quality-standards.md`.

## Dependências

- **Bloqueada por:** nada. Independe do EPIC-010.
- **Bloqueia:** nada.

## Decisões já tomadas (não as reabra)

- IDR-010 — snapshot imutável, sem recálculo retroativo. Esta estória lê o snapshot; não o altera.
- IDR-008 — tokens de design, sem hex literal.
- Design system e padrão mobile-first do relatório atual.

## Liberdade técnica do agente

Você decide como extrair e formatar os valores a partir do snapshot e como compor a apresentação. Você **não** decide quais indicadores existem, sua ordem, nem pode tocar no motor.

Se descobrir que algum indicador não tem valor nominal derivável do snapshot atual, **registre nas notas e siga** — não altere o motor para produzi-lo. Se vários indicadores estiverem nessa situação, pare e avise o PO: pode ser sinal de que o pedido exige mudança de motor, e aí é outra estória.

## Definição de Pronto

- [x] CA-1 a CA-11 passam.
- [ ] Pipeline verde; deploy em homologação verificado.
- [x] `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### 2026-08-04 — Leitura inicial completa

- **Documentos lidos:** STORY-044 (inteira — frontmatter, contexto, CA-1..CA-11, fora de escopo, decisões já tomadas, DoD), `skills/programador/SKILL.md` + `references/reading-discipline.md`, `skills/po/references/agent-task-format.md` + `quality-standards.md`, `skills/designer/references/collaboration-with-developer.md`, `revisao/analise-po-feedback-jose-marques-2026ago03.md` (§1 item A, §2, F1/F4 — F4 explicita que "os valores nominais continuam sendo exibidos normalmente" e que os pedidos A e C se reforçam), `decisions/idr/IDR-010` (inteira — sub-decisões 2, 3, 4, 5). Código: `app/Domain/Motor/Motor.php`, `IndicadorResultado.php`, `QuizPayloadCanonicalizer.php`, `Calculos/DreAdaptada.php`, os **15** indicadores de `Domain/Motor/Indicadores/`, `app/Actions/CalcularDiagnostico.php`, `app/Models/Diagnostico.php`, `app/Http/Controllers/DiagnosticoController.php`, `app/Support/Relatorio/IndicadorFormatter.php`, `resources/views/diagnosticos/show.blade.php`, `components/relatorio/{linha-indicador,card-indicador,card-informativo,farol}.blade.php`, `resources/views/livewire/diagnostico/quiz.blade.php` (rótulos Q02..Q16), `config/quiz/help-industria.php` (Q13), `database/factories/DiagnosticoFactory.php`, `tests/Feature/Http/DiagnosticoShowTest.php`, `tests/Unit/Support/Relatorio/IndicadorFormatterTest.php`, `tests/Pest.php`, `phpunit-domain.xml`.

- **Entendimento consolidado:** estória de apresentação pura. O relatório já lê `indicadores_calculados` do snapshot e mostra o indicador formatado; falta mostrar, ao lado, os dois (ou três) números em reais que produziram aquele indicador. **Onde esses números vivem:** não em `indicadores_calculados` — esse bloco só tem `{valor, farol, motivo, mensagem}` (verificado em `IndicadorResultado::toArray()`, não presumido). Vivem em `quiz_payload`, que é a **outra coluna do mesmo snapshot persistido**, já canonicalizada no INSERT (`CalcularDiagnostico::execute()` grava `$canonical`). Logo, "derivar do snapshot" = ler `quiz_payload` e agregar. Isso não invoca o motor, não escreve nada e não toca `payload_hash` nem `motor_version` — as três proibições do CA-2 continuam intactas. Ver "Decisões tomadas" para por que a alternativa (carimbar os nominais em `indicadores_calculados`) está **proibida** por esta própria estória.

- **Dúvidas:** nenhuma bloqueante. Uma descoberta que o PO precisa ver está em "Descobertas" (6 dos 15 indicadores não têm valor nominal derivável — e não teriam nem com mudança de motor). A estória manda registrar e seguir; a instrução do PO na abertura da sessão repetiu "registre nas notas e siga".

- **Plano:**
  1. `App\Support\Relatorio\ValoresNominais` — camada de apresentação, recebe o `quiz_payload` persistido, devolve `list<array{rotulo, valor}>` já formatado em BR por código de indicador; `[]` quando não há nominal aplicável. Reusa `DreAdaptada` em modo leitura (sem instanciar `Motor`).
  2. `IndicadorFormatter::moeda()` promovido a público (era `moedaBr` privado) — uma só implementação de moeda BR no relatório.
  3. `linha-indicador` (tabela desktop) e `card-indicador` / `card-informativo` (cards mobile) passam a renderizar o bloco de apoio, com hierarquia tipográfica subordinada e tokens do IDR-008.
  4. TDD: unit do extrator primeiro (é onde mora a regra), depois Feature sobre o HTML renderizado, depois Dusk em browser real a 360×800 e desktop.
  5. IDR registrando a política de derivação na apresentação + o risco de drift aceito.

- **Testes que pretendo escrever** (cada CA com a borda que realmente pode quebrar, não só o caminho feliz):
  - **Unit `ValoresNominaisTest`:** os 9 indicadores com nominal, um a um, com valores exatos (não `toContain`); os 6 sem nominal devolvendo `[]`; código desconhecido → `[]`; payload vazio → `[]` em todos; **cada termo faltando isoladamente** (Q08 ausente derruba margem bruta mas não derruba giro do ativo) — é a borda que o `null` em cascata da `DreAdaptada` produz; valor negativo (lucro bruto < 0, dívida líquida < 0) formatado com sinal; valor zero; string numérica vs int no payload (o canonicalizador não força tipo — os dois chegam).
  - **Feature (CA-1, CA-3, CA-4, CA-8, CA-9):** render com nominais presentes; indicador indisponível continuando com a mensagem semântica **e sem** bloco nominal; snapshot legado sem as chaves do payload renderizando 200 sem o bloco; ausência de placeholder/"N/A" quando não há nominal (asserção negativa); `payload_hash`, `motor_version`, `matrix_version` e `indicadores_calculados` **byte a byte idênticos** antes e depois do GET (CA-10 — o teste que prova a restrição dura).
  - **Dusk (CA-7, CA-5):** 360×800 sem scroll horizontal (`scrollWidth <= clientWidth`) e desktop 1280 com a tabela intacta.

### Decisões tomadas

- **A fonte do valor nominal é o `quiz_payload`, não o `indicadores_calculados` — e isso não é atalho, é a única saída que a estória permite.** O bloco `indicadores_calculados` guarda só `{valor, farol, motivo, mensagem}`; os agregados que produziram o indicador (Lucro Bruto, EBITDA, LOL, AT, PL, NCG) **nunca foram carimbados lá** — existem em memória dentro da `DreAdaptada` durante a execução e são descartados. Carimbá-los agora significaria mudar a saída de `Motor::calcular()` → bump de `motor_version` → proibido pelo CA-2 e pelo "Fora de escopo". O `quiz_payload` é a outra coluna do **mesmo** snapshot persistido, canonicalizada no `INSERT` e imutável. Derivar dela é leitura de snapshot; o motor não é instanciado em nenhum ponto do caminho de leitura. Registrado em **IDR-013**.

- **`DreAdaptada` reusada em leitura, não reimplementada.** A tentação era escrever a aritmética na camada de apresentação para não depender de `app/Domain`. Recusei: duplicar "EBITDA = LB − DF − DV" criaria duas fontes de verdade para o mesmo número dentro do mesmo produto — que é literalmente o defeito de confiança apontado no §F2 do parecer do PO ("duas telas do mesmo produto discordando sobre o mesmo número destrói a confiança mais rápido do que qualquer bug"). A `DreAdaptada` é pura (sem `now()`, sem banco, sem Auth) e já devolve `null` por insumo faltante. Os únicos dois agregados que ela não expõe — dívida líquida (Q06 − Q02) e NCG (Q03 + Q04 − Q07) — são composições dos accessores dela, não fórmulas novas. O trade-off (drift se uma fórmula da DRE mudar em bump MAJOR) está aceito e com gatilho de reabertura escrito no IDR-013.

- **Termo faltante zera o indicador inteiro, em vez de exibir o par pela metade.** "Lucro bruto R$ 600.000,00 sobre —" mente mais do que não mostrar nada: sugere que o denominador é zero. A resiliência é **por indicador**, não global — snapshot antigo com payload parcial perde só os indicadores afetados e mantém os derriváveis (há teste para os dois lados).

- **Rótulos dizem "(12 meses)" nos agregados anualizados.** O quiz captura média **mensal** (Q08/Q09/Q14/Q15/Q16) e a DRE anualiza ×12. Mostrar "Vendas: R$ 1.200.000,00" para quem digitou 100.000 recriaria exatamente o erro de ordem de grandeza que a estória veio combater (é o mesmo risco do §F1 do parecer, "em mil reais"). O sufixo custa 11 caracteres e fecha a ambiguidade. Os termos de balanço (saldos) não levam sufixo, porque não são anualizados.

- **Nomenclatura dos termos de balanço copiada dos rótulos do quiz** ("Contas a receber", "Estoque", "Fornecedores a pagar"), não do jargão do Anexo B ("clientes", "PCC"). O usuário confere contra o que ele digitou; usar outra palavra para o mesmo campo obrigaria tradução mental.

- **Catálogo `CODIGOS_COM_NOMINAL` explícito e travado por teste**, em vez de derivado da lista do motor. Indicador novo não ganha nominal em silêncio nem fica sem ele em silêncio: ou entra na lista por decisão consciente, ou o teste de contrato falha.

- **Coluna "Valor" da tabela desktop ganhou largura reservada (`w-64`).** Sem ela a coluna encolhia contra a "Mensagem" e cada par quebrava entre rótulo e número, ficando ilegível — verificado em screenshot, não presumido. Além disso o número em si nunca parte no meio (`whitespace-nowrap` no valor), então a quebra, quando ocorre, cai antes do "R$".

- **`whitespace-nowrap` movido da célula para o valor do indicador.** Estava na `<td>` inteira; com o bloco nominal dentro, isso daria scroll horizontal na tabela densa. É a mudança que o CA-7 realmente cobrava.

- **Nenhum arquivo de `app/Domain/` foi tocado.** `git diff --stat` confirma: as alterações são `app/Support/Relatorio/` + 4 Blades + testes. `motor_version` segue `1.3.1`, os golden hashes rodam idênticos.

### Descobertas

- **[PARA O PO] Seis dos quinze indicadores não têm valor nominal derivável — e não teriam nem com mudança de motor.** São `pmc`, `pme`, `pmr` (declarados diretamente em dias nos Q10/Q11/Q12), `ciclo_financeiro` e `ciclo_operacional` (somas desses dias) e `inadimplencia` (Q13, percentual declarado). A estória previa que eu parasse e avisasse se "vários" caíssem nessa situação; a instrução de abertura da sessão mandou registrar e seguir, e o CA-3 já define o comportamento ("quando não houver valor nominal aplicável, nada é exibido"). Sigo, mas com uma correção importante ao diagnóstico presumido na estória: **isto não é sinal de que o pedido exige mudança de motor.** O motor não consegue produzir esses números porque eles **não existem no produto** — o usuário nunca informou o valor monetário do estoque médio girado, nem das vendas a prazo que sustentam a inadimplência. Só uma **pergunta nova no quiz** os criaria, o que é decisão de produto (mais atrito no formulário, contra a direção do §F11 do próprio parecer), não decisão de motor. O caso mais tentador é a inadimplência: o `help` do Q13 fala em "de cada R$ 100 vendidos a prazo, R$ 5 atrasam", mas "vendas a prazo" não é campo capturado — usar Contas a Receber (Q03) como base seria inventar um denominador que a fórmula não usa. Não inventei. **Os 9 restantes cobrem todos os indicadores sobre os quais o Key User reclamou** ("22% de quanto?"): as três margens, as duas razões sobre EBITDA, fontes de recursos, giro do ativo e as duas leituras de NCG.

- **O bundle CSS é pré-buildado e não é versionado — classe utilitária nova não existe até `npm run build`.** Custou um ciclo de diagnóstico errado: apliquei largura na coluna, o screenshot não mudou, e a primeira hipótese (errada) foi "o algoritmo `table-layout: auto` está ignorando `width` no `<th>`". Não era: a classe simplesmente não estava no CSS servido. `public/build` está no `.gitignore` e a imagem de deploy roda `npm run build` (`infra/docker/Dockerfile:36`), então **nada precisa ser commitado** — mas quem valida visualmente em ambiente local precisa rebuildar antes de acreditar no que vê. Isto é o irmão do gotcha do Tailwind v4 já documentado em `farol.blade.php` (tree-shake de token que não aparece dentro de `class=`).

- **`min-w-[17rem]` não foi emitido pelo Tailwind v4 mesmo após o build**, enquanto `w-64`, `whitespace-normal` e `gap-y-0.5` foram. Não investiguei a fundo porque a largura no `<th>` resolveu e código morto não fica — mas fica o registro: em arbitrary values, conferir no CSS gerado em vez de assumir que a classe existe.

- **O Postgres reordena as chaves de uma coluna `jsonb` na gravação.** O objeto em memória logo após o `INSERT` e o mesmo registro relido do banco produzem `json_encode` diferentes — mesma informação, ordem de chaves distinta. Isso derrubou a primeira versão do teste do CA-10, que comparava via model e acusava alteração onde não havia. A comparação correta é linha crua contra linha crua (`DB::table(...)`), e é isso que o teste faz agora. Não é defeito: a garantia bit-exata de IDR-010 vive no `payload_hash`, calculado **antes** do `INSERT` sobre o JSON canônico — não na ordem física das chaves no `jsonb`.

- **Fora de escopo, para o PO: `IDR-008` e `IDR-012` existem em `decisions/idr/` mas não constam de `decisions.idr` no `index.json`.** Não os adicionei — cadastrar decisão alheia no índice exigiria interpretar o conteúdo delas, e o índice é fonte de verdade do PO. Registro aqui para não ficar invisível.

- **O `card-informativo` do NCG absoluto ganhou os três componentes** (Contas a receber, Estoque, Fornecedores a pagar) mesmo o indicador já sendo um valor em reais. O CA-1 pede "o valor **ou** os valores nominais que o compõem"; para uma diferença de três parcelas, ver as parcelas é o que responde "por que meu NCG é R$ 110 mil?".

### Bloqueios encontrados

Nenhum. A única decisão que chegou perto de escalonamento — os 6 indicadores sem nominal — está resolvida pelo CA-3 e registrada em "Descobertas" com a correção de diagnóstico para o PO.

### IDRs criados

- **IDR-013 — Agregados monetários de exibição derivados do `quiz_payload` na camada de apresentação** (`decisions/idr/IDR-013-derivacao-valores-nominais-apresentacao.md`, `proposed`). Fixa de onde vem agregado monetário de exibição, que fórmula financeira mora só na `DreAdaptada`, e qual PR futuro obriga a reabrir a decisão (qualquer bump MAJOR que mude fórmula da DRE).

### Cobertura final

- **Pest (suíte `All`): 912 testes / 2783 asserções — verde.** Baseline antes da estória: 870. Nenhum teste pré-existente quebrado.
- **Dusk (browser real): 29 testes / 171 asserções — verde**, incluindo os 3 novos (mobile 360×800 sem scroll horizontal, desktop 1280 sem estouro, dominância tipográfica do indicador medida por `getComputedStyle`).
- **Cobertura geral: 97,4 %** (gate 80 %). `Support/Relatorio/ValoresNominais` **100 %**, `Support/Relatorio/IndicadorFormatter` **100 %** → CA-11 atendido.
- **Cobertura de domínio: 99,6 %** (gate 98 %) — inalterada, como esperado de uma estória que não toca `app/Domain`.
- **Pint:** 242 arquivos, limpo. **PHPStan:** sem erros.
- **Evidência visual:** screenshots desktop (1280) e mobile (360) do relatório renderizado conferidos durante a execução.
- **Commit:** `379c355` em `main` local (sem push — workflow do projeto exige pedido explícito do PO para push/PR/tag).
- **Testes novos:** `tests/Unit/Support/Relatorio/ValoresNominaisTest.php` (regra de composição, bordas de faltante/negativo/zero/tipo), `tests/Feature/Http/DiagnosticoValoresNominaisTest.php` (HTML renderizado + prova do CA-2/CA-10 por comparação da linha crua), `tests/Browser/ValoresNominaisBrowserTest.php` (CA-5/CA-7 em navegador real).
