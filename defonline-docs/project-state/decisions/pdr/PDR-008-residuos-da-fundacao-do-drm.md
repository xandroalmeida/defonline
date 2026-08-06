---
pdr_id: PDR-008
slug: residuos-da-fundacao-do-drm
title: Quatro resíduos da fundação do EPIC-010 — contrato da linha do snapshot, escopo da STORY-055, dívidas de acessibilidade herdadas e redação da invariante 9
status: accepted
decided_at: 2026-08-06
decided_by: PO (Alexandro / Claude)
related_epics: [EPIC-010, EPIC-004, EPIC-007]
related_stories: [STORY-046, STORY-048, STORY-049, STORY-053, STORY-055, STORY-059]
related_adrs: [ADR-007, ADR-008]
related_ddrs: [DDR-004]
related_pdrs: [PDR-002, PDR-006, PDR-007]
schema_change: "invariante 9 — redação revista em skills/po/references/indexing.md"
---

# PDR-008 — Resíduos da fundação do EPIC-010

## Contexto

O `PDR-007` aprovou as specs e a `ADR-008`, e deixou quatro itens explicitamente abertos por não bloquearem o Checkpoint 1. Nenhum deles é grande; todos são do PO porque mudam contrato, critério de aceite ou esquema — nunca implementação.

Registro os quatro juntos porque saíram do mesmo ciclo de entrega e porque três deles têm a mesma forma: **um agente encontrou algo que não era dele, não decidiu sozinho, e escalou.** Essa é a mecânica funcionando; o custo de mantê-la é responder.

---

## Decisão 1 — `tipo` e `destaque` entram no contrato da linha do snapshot

**A questão.** Duas regras da Tela 2 dependem hoje de configuração no código: quais linhas são os três destaques (quanto vendeu / sobrou antes / sobrou depois) e onde um card de trecho fecha no mobile. O snapshot não diz o suficiente sobre cada linha para que a tela saiba isso sozinha.

**Decisão.** Cada linha da `demonstracao` passa a carregar **`tipo`** (`total | deducao | subtotal | item`) e **`destaque`** (bool). Vira o **CA-1e da `STORY-048`**.

O fundamento é o mesmo com que o `ADR-007` mandou persistir os rótulos das linhas, e que o aditivo de 04/08 do `IDR-010` já tinha aprendido da pior forma: **o que a tela precisa para renderizar o passado tem que estar no passado.** Configuração vigente hoje não é contrato — muda, e quando muda, meses fechados passam a ser lidos com regra que não era a deles.

**O que decidiu foi a janela, não o mérito.** O motor não foi construído. Hoje isso são dois campos num contrato que ainda é papel. Depois da `STORY-048`, snapshot é imutável (`ADR-007`, `IDR-010`): os meses já fechados nunca ganhariam os campos, a configuração paralela viraria permanente, e estaríamos no beco que o `IDR-013` já registrou uma vez neste produto.

A tela não dependia disso — havia fallback especificado, que agora vira salvaguarda em vez de mecanismo principal.

## Decisão 2 — Os critérios de aceite da STORY-055 estão corrigidos, e a impressão mora nela

**A questão.** Os CAs da `STORY-055` foram escritos quando o PDF estava no escopo dela. A `ADR-008` mudou isso, e o Arquiteto registrou que alterar CA não é alçada dele.

**Decisão.** Os critérios foram ajustados:

| CA | Antes | Agora |
|---|---|---|
| CA-1 | "formato de planilha" | **XLSX** via `phpoffice/phpspreadsheet` — CSV não atende |
| CA-3 | PDF A4, ou `n/a` se o ADR adiar | **`n/a`, resolvido** — não implemente PDF, não instale motor |
| CA-4 | escopo "conforme o ADR" | um arquivo, **duas abas**, sem seletor de intervalo, com fórmulas |
| CA-9 | "se for assíncrono, dê feedback" | **síncrono**, `streamDownload()`, nunca armazenado |
| CA-10 | "verificação de conteúdo" | **relê o XLSX e avalia as fórmulas** contra o snapshot |
| CA-12/13 | não existiam | camada `@media print` + o botão que **não** se chama "Exportar PDF" |

**A camada de impressão fica na `STORY-055`, não na `STORY-049`.** As duas alocações eram defensáveis: na 049, o usuário ganha papel já no Checkpoint 1. Escolhi a 055 porque o Checkpoint 1 tem uma única pergunta a responder — *o usuário lança um mês e vê se o negócio deu lucro?* — e cada coisa boa que se pendura nele atrasa a resposta. Impressão é saída, e saída é o Checkpoint 3.

O **CA-13** existe para uma armadilha específica: uma saída interina que se apresenta bem demais deixa de ser interina. Se o botão disser "Exportar PDF", o EPIC-007 perde urgência e o usuário fica permanentemente com o PDF do navegador — exatamente o que recusei no `PDR-007` §4.

## Decisão 3 — As duas dívidas de acessibilidade herdadas vão para a STORY-059

**A questão.** Ao especificar as telas do DRM, o Designer encontrou dois defeitos **já em produção**: o `<x-help>` tem alvo de toque de 18×18px contra o piso de 44px, e a borda de input mede 1,23:1 contra os 3:1 da WCAG 1.4.11. Nenhum dos dois nasceu da estória dele.

**Decisão.** Viram o **CA-9** e o **CA-10 da `STORY-059`**, que já é a estória de dívida de acessibilidade. É a mesma regra do `PDR-006` §1: dívida herdada não fica escondida dentro de uma estória cujo título não fala dela, e também não vira estória nova a cada achado.

- **CA-9 (`<x-help>`) é bug, não decisão** — área de toque por padding transparente, sem aumentar o círculo visível. Não precisa de DDR. **É o mais urgente dos dois**, e por um motivo de sequência: está no Quiz hoje, e o formulário do DRM usa `<x-help>` em dezenas de campos. Entregar a `STORY-047` antes de corrigir multiplica o defeito por dezenas em vez de por um.
- **CA-10 (borda de input) exige DDR do Designer**, como o CA-3 já exigia: mexe num token da fundação e reavalia todas as telas de formulário em produção. **Não segura a estória** — se o DDR não estiver pronto, os demais CAs fecham e este fica explicitamente aberto nas notas.

A estória sobe de `S` para `M`.

## Decisão 4 — A invariante 9 do índice passa a aceitar `implemented_by`

**A questão.** A invariante 9 exige, para toda estória com `requires_design: true`, uma entrada em `design.screens[]` com `story == story.id`. As specs do DRM nasceram de uma estória de design dedicada, então carregam `story: STORY-046` e `implemented_by: STORY-047 | 049 | 051`. Numa leitura literal, a `STORY-047` reprova — apesar de ter spec `ready`, apontada pelo seu `design_screen_id`.

**Decisão.** A redação passa a aceitar **`story` OU `implemented_by`**. Alterado em `skills/po/references/indexing.md`.

A regra original não estava errada — estava **incompleta**. Ela descrevia o modelo paralelo do `PDR-002`, em que a spec nasce dentro da própria estória de implementação. O modelo de estória de design dedicada apareceu depois, e ninguém releu a invariante.

**Corrijo em vez de ignorar por um motivo prático:** a invariante 9 já estava frouxa — a `STORY-044` está `done` com `requires_design: true` e sem entrada nenhuma. Uma regra que falha em casos legítimos ensina a ignorá-la, e uma regra que se aprende a ignorar não protege mais nada no dia em que uma UI de verdade for para revisão sem spec.

**O que não muda:** a intenção protetiva. Toda estória de UI continua tendo que ter spec antes de ir para revisão. A invariante 10 (`design_screen_id` aponta para entrada existente) faz o caminho de volta e continua valendo.

---

## Efeitos

- `STORY-048` ganha o CA-1e — **antes de começar**, que é a única hora em que isso é barato.
- `STORY-055` tem escopo, mecânica e testes alinhados à `ADR-008`, e recebe a camada de impressão.
- `STORY-059` vai a cinco achados nomeados e sobe para `M`; recomendada **antes** da `STORY-047` por causa do CA-9.
- A invariante 9 volta a ser verificável. `STORY-047`, `049` e `051` passam a satisfazê-la de fato, não por interpretação.

Com isto, **não resta nenhum item escalado em aberto** da fundação do EPIC-010.
