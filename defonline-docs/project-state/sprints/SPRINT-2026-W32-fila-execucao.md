---
sprint_id: SPRINT-2026-W32
type: fila-execucao
created_at: 2026-08-04
created_by: po (Alexandro / Claude)
destino: Claude Code
---

# Fila de execução — SPRINT-2026-W32

Cada bloco abaixo é um **prompt pronto para colar no Claude Code**, um por sessão. Execute na ordem. Não junte dois numa sessão só — as estórias foram dimensionadas para caber em uma sessão cada.

**Antes de começar qualquer coisa:** o repositório tem `AGENTS.md` na raiz, que manda carregar a skill do papel correspondente. Os prompts abaixo já fazem isso. As estórias são autocontidas — o agente não precisa de contexto desta conversa.

**Regra que vale para todos:** ao terminar, o agente atualiza o frontmatter da estória, preenche "Notas do agente", atualiza `index.json` e abre PR. Está no protocolo de cada estória; os prompts reforçam.

---

## Bloco 1 — Limpeza da onda 1 (fecha o EPIC-002)

Quatro estórias de programação. Ao fim, um único deploy em homologação fecha também a **STORY-039** e a **STORY-040**, que estão em `in_review` desde junho só aguardando verificação — e o EPIC-002 sobe para `done`.

### 1.1 — STORY-041 (XS)

```
Carregue a skill `programador` (ver AGENTS.md).

Execute integralmente a estória:
defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/stories/STORY-041-plural-acoes-veredito.md

Leia a estória inteira antes de começar. Siga o protocolo do agente ao final dela.
```

### 1.2 — STORY-042 (M)

```
Carregue a skill `programador` (ver AGENTS.md).

Execute integralmente a estória:
defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/stories/STORY-042-pacote-conteudo-ebc.md

Atenção: a estória tem 2 itens com pendência ao Edvan e um default definido caso
não haja resposta. O default já venceu — aplique-o e registre nas notas.

Leia a estória inteira antes de começar. Siga o protocolo do agente ao final dela.
```

### 1.3 — STORY-043 (S) — depende da 1.2

```
Carregue a skill `programador` (ver AGENTS.md).

Execute integralmente a estória:
defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/stories/STORY-043-validacao-cruzada-q17-recebiveis.md

Ela é aditiva à engine declarativa de validações cruzadas da STORY-034 e depende
da STORY-042 já estar entregue. Confirme isso antes de começar.

Leia a estória inteira antes de começar. Siga o protocolo do agente ao final dela.
```

### 1.4 — STORY-044 (S)

```
Carregue a skill `programador` (ver AGENTS.md).

Execute integralmente a estória:
defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/stories/STORY-044-valor-nominal-indicadores.md

Restrição dura: esta estória é de APRESENTAÇÃO. Lê o snapshot já persistido do
diagnóstico. Não toca no motor, não altera payload_hash, não muda motor_version.
Se descobrir que algum indicador não tem valor nominal derivável do snapshot
atual, registre nas notas e siga — não altere o motor para produzi-lo.

Leia a estória inteira antes de começar. Siga o protocolo do agente ao final dela.
```

### 1.5 — Deploy e fechamento do EPIC-002

```
Carregue a skill `programador` (ver AGENTS.md).

1. Gere a próxima tag rc rodando o workflow `bump-rc` (ele agora só SUGERE a tag —
   quem cria é o humano; o push da tag é o que dispara o deploy).
2. Crie e empurre a tag, acompanhe o `release-homolog.yml` até o fim.
3. Verifique em homologação os critérios de aceite pendentes de deploy das
   STORY-039 e STORY-040, que estão em `in_review` desde 2026-06-02.
4. Se passarem: atualize as duas para `done` no frontmatter e no index.json.
5. Com 039 a 044 todas em `done`, promova o EPIC-002 de `in_review` para `done`
   no index.json (ver campo `promote_to_done_when` no próprio épico).

Registre tudo nas notas das estórias envolvidas.
```

---

## Bloco 2 — Fundação do EPIC-010 (nenhum código de produção)

Três estórias que produzem **decisões e specs**, não implementação. Podem rodar em paralelo ao Bloco 1 se você tiver mais de uma sessão — são papéis diferentes e não tocam nos mesmos arquivos.

Antes da primeira, vale uma leitura de contexto (o agente faz sozinho, mas ajuda saber): o parecer em `defonline-docs/revisao/analise-po-feedback-jose-marques-2026ago03.md` explica por que o módulo existe.

### 2.1 — STORY-045 (spike, arquiteto) — **a mais importante da sprint**

```
Carregue a skill `arquiteto` (ver AGENTS.md).

Execute integralmente a estória:
defonline-docs/project-state/epics/EPIC-010-drm-demonstracao-resultado-mensal/stories/STORY-045-spike-modelo-versionamento-retificacao.md

Esta decisão destrava todo o EPIC-010 — nenhuma estória de implementação do
épico pode começar antes dela. O ponto central: o DRM é o primeiro objeto
retificável do produto, e isso colide com o IDR-010 (snapshot imutável do
Diagnóstico). O IDR-010 NÃO é revogado; a questão é como acomodar um objeto
retificável ao lado de um imutável.

São 8 perguntas a responder. Leia a estória inteira antes de começar.
```

### 2.2 — STORY-053 (spike, arquiteto)

```
Carregue a skill `arquiteto` (ver AGENTS.md).

Execute integralmente a estória:
defonline-docs/project-state/epics/EPIC-010-drm-demonstracao-resultado-mensal/stories/STORY-053-spike-exportacao.md

A decisão extrapola o EPIC-010: o EPIC-007 (PDF do relatório de Diagnóstico)
depende dela. Valide a recomendação contra os DOIS casos de uso.

Saída legítima e prevista: concluir que o PDF é caro demais para agora e
recomendar entregar só a exportação em planilha, devolvendo o PDF ao EPIC-007.
Se for essa a conclusão, diga com todas as letras.

Leia a estória inteira antes de começar.
```

### 2.3 — STORY-046 (design)

```
Carregue a skill `designer` (ver AGENTS.md).

Execute integralmente a estória:
defonline-docs/project-state/epics/EPIC-010-drm-demonstracao-resultado-mensal/stories/STORY-046-design-telas-drm.md

Três specs de tela. O problema real está na primeira: até 38 campos monetários,
doze vezes por ano, preenchidos no celular à noite. Se a tela de lançamento for
uma lista de 38 campos, o módulo morre no segundo mês.

O PDR-003 já fixou os três mecanismos anti-atrito (modo agregado por grupo,
pré-preenchimento do mês anterior, rascunho retomável). Seu trabalho é fazê-los
funcionar visualmente.

Reaproveite os padrões do Quiz e do relatório de Diagnóstico que já estão em
produção. Não crie um segundo dialeto de formulário dentro do mesmo produto.

Leia a estória inteira antes de começar.
```

---

## Bloco 3 — Conteúdo do DRM (opcional nesta sprint)

Pode entrar se sobrar espaço, ou ir para a sprint seguinte. Não bloqueia nada.

### 3.1 — STORY-052 (PO)

```
Carregue a skill `po` (ver AGENTS.md).

Execute integralmente a estória:
defonline-docs/project-state/epics/EPIC-010-drm-demonstracao-resultado-mensal/stories/STORY-052-pacote-conteudo-ebc-drm.md

CA-1 e CA-2 já estão concluídos (PDR-004). O foco é o restante: tooltips,
mensagens de alerta, textos de leitura e — o item que mudou em 04/08 — a
ESTRUTURA paramétrica das faixas de farol.

Regra dura do CA-5: entregue a estrutura com as faixas em estado
`nao_calibrada`. Faixa não calibrada NÃO produz farol. A EBC popula os valores
depois, por edição de configuração, sem mudança de código.

Leia a estória inteira antes de começar.
```

---

## Ordem recomendada

Com **uma sessão por vez**: `1.1 → 1.2 → 1.3 → 1.4 → 1.5 → 2.1 → 2.2 → 2.3`.

O Bloco 1 primeiro porque termina num deploy que fecha um épico inteiro — marco limpo, e o repositório fica sem trabalho pela metade antes de o DRM começar.

Com **sessões em paralelo**: rode o Bloco 1 e o Bloco 2 ao mesmo tempo. Papéis diferentes, arquivos diferentes, sem conflito de merge. É o cenário para o qual a sprint foi desenhada.

## Quando esta fila termina

O EPIC-002 estará fechado, a `STORY-047` (lançamento mensal do DRM) poderá abrir sem nenhuma decisão pendente à frente, e a próxima sprint entrega o **Checkpoint 1**: o usuário lança um mês e vê se o negócio deu lucro.

## O que NÃO está nesta fila

- Qualquer implementação do DRM — só começa depois que 2.1 e 2.3 fecharem.
- `STORY-058` (spike de provisão de férias e 13º) — depende da 2.1 e pertence ao Checkpoint 3.
- EPIC-003 — adiado pelo PDR-005.
- Beta com usuários reais — frente de negócio, fora do fluxo de sprint.
