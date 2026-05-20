# Disciplina de leitura — entender antes de codar

> Antes de escrever **uma única linha de código**, você lê e entende. Pressa para começar a codar é a fonte número um de retrabalho.

Esta disciplina parece óbvia. Não é. É **a mais quebrada** sob pressão, e é a mais cara quando quebrada. Programador sênior **lê devagar** — releia documentos citados em vez de confiar no resumo, pergunta quando algo está dúbio **antes** de codar (não depois).

## A mentalidade

Programar é, em grande medida, **traduzir entendimento em código**. Se o entendimento está raso ou errado, o código também estará — independente de quão boa for sua técnica depois. Voltar atrás de uma decisão construída sobre um entendimento incorreto é mais caro que ter parado para ler direito no início.

A pressão psicológica para começar a digitar logo existe — você sente que "está produzindo". Mas escrever código baseado em entendimento parcial é **produzir dívida**, não progresso. Velocidade real vem de ter entendido o que precisa ser feito.

---

## O que ler — para qualquer estória

Antes de tocar o teclado, leia **na ordem**:

### 1. A estória inteira (`STORY-XXX-*.md`)

**Não pule para "o quê fazer".** Leia tudo:

- Frontmatter (ID, épico, sprint, tipo, status, tamanho estimado).
- Contexto — por que esta estória existe.
- O quê (objetivo).
- Por quê (valor para o usuário).
- **Todos os critérios de aceite** — cada CA é um teste futuro.
- Fora de escopo — saber o que **NÃO** fazer.
- Padrões de qualidade aplicáveis.
- Dependências (bloqueada por, bloqueia, pré-requisitos).
- **Decisões já tomadas** (ADRs/PDRs referenciados — você é obrigado a respeitá-las).
- Definição de Pronto.
- Protocolo do agente.
- Notas existentes (se a estória já passou por alguém antes — leia tudo).

### 2. Os documentos referenciados na estória

Não confie no resumo na estória. **Vá nos documentos:**

- Especificação funcional, **nas seções citadas** (e adjacentes — contexto importa).
- ADRs vigentes que se aplicam.
- PDRs relacionados.
- IDRs anteriores que afetam este código.

Se a estória cita `defonline-docs/especificacao/V2/especificacao-funcional.md` seção 4.2, você abre o arquivo, lê a 4.2 **e dá uma olhada na 4.1 e 4.3 também**. Geralmente o contexto da seção adjacente esclarece nuances.

### 3. As skills e suas referências

Se for sua primeira estória do dia, ou se faz tempo:

- Sua `SKILL.md` (Programador).
- `references/coding-principles.md`.
- `references/testing-discipline.md`.
- `references/library-discipline.md`.
- `references/done-checklist.md`.
- Protocolo em `defonline-docs/skills/po/references/agent-task-format.md`.

Re-ler estas referências leva poucos minutos e evita tantas armadilhas (esquecer cobertura, esquecer caso inválido, esquecer atualizar `index.json`) que vale o investimento.

### 4. O código existente que vai ser tocado

**Não comece a escrever sem olhar como o módulo já é organizado.** Leia:

- A estrutura de pastas do módulo a ser tocado.
- Arquivos relacionados — não só o que você vai mexer.
- Testes existentes do módulo — eles te mostram o estilo de teste do projeto.
- Padrões em uso no projeto (mesmo não documentados — você respeita).

Frequentemente, ao ler o código existente você descobre que metade do trabalho já está feito por algum helper que você ia recriar.

---

## Como ler

- **Devagar.** A primeira passada é leitura de **compreensão**, não busca.
- **Com caneta na mão** (figurativamente — use "Notas do agente"). Anote dúvidas, ambiguidades, decisões implícitas que precisam ser confirmadas.
- **Releia trechos críticos.** Critérios de aceite merecem leitura dupla. Se o CA tem alguma palavra ambígua ("automaticamente", "rapidamente", "se necessário"), tripla.
- **Construa um modelo mental.** Você consegue **descrever em voz alta**, em ordem, o que vai construir e testar? Se não consegue, ainda não entendeu — releia.

---

## O checkpoint de entendimento

Antes de começar a codar, faça este checkpoint comigo mesmo (de cabeça, sem olhar a estória):

- [ ] Eu consigo **enumerar os critérios de aceite** sem olhar?
- [ ] Eu consigo descrever o **caminho feliz** do fluxo do começo ao fim?
- [ ] Eu consigo listar **3+ casos inválidos / exceções / bordas** que precisam ser testados?
- [ ] Eu sei **quais decisões arquiteturais (ADRs)** restringem esta implementação?
- [ ] Eu sei o que está **fora de escopo** (e por isso não vou começar a fazer)?
- [ ] Eu sei o que **define a estória como pronta** (DoD)?

**Se algum item está ❌, não comece. Volte a ler.**

Esse checkpoint custa 60 segundos e poupa horas.

---

## Ambiguidade: identificar e tratar **antes** de codar

Ambiguidades aparecem na leitura — não no meio do código. Trate-as **agora**:

| Tipo de ambiguidade | O que fazer |
|---|---|
| CA não está claro ou está vago | Pare. Pergunte ao PO via "Notas do agente". `status: blocked`. |
| Comportamento esperado em caso de borda não está definido | Idem — **não invente**. |
| Dois ADRs/PDRs parecem conflitar | Escalar para Arquiteto ou PO conforme o caso. |
| Não sei se uma decisão é minha ou do Arquiteto | Pergunte ao Arquiteto via "Notas do agente". |
| Termo ambíguo na estória ("rápido", "leve", "amigável", "robusto") | Pergunte ao PO o que é mensurável. |
| Documento referenciado parece desatualizado em relação a outro | Escalar para PO/Arquiteto antes de assumir qualquer um. |

**Anti-padrão clássico:** "vou começar pela parte óbvia e quando chegar na parte dúbia eu vejo". Quando você chegar na parte dúbia, vai estar com 2 horas de código investido e tendência a "fazer algo que se aproveite" — vai inventar. Vai errar. Vai ter retrabalho.

**A regra:** dúvida sobre o que fazer é resolvida **antes** de codar.

---

## Notas do agente: registre a leitura

Logo após carregar contexto, **antes** de codar, faça uma primeira entrada em "Notas do agente" da estória:

```markdown
### <YYYY-MM-DD> — Leitura inicial completa
- **Documentos lidos:** STORY-XXX (inteira), ADR-YYY (X.Y), PDR-ZZZ, spec funcional seção W.X.
- **Entendimento consolidado:** <2-3 frases descrevendo, com suas palavras, o que vai construir e por quê>.
- **Dúvidas:** <lista, ou "nenhuma">.
- **Plano:** <esqueleto da abordagem em 3-5 bullets>.
- **Testes que pretendo escrever:** <lista resumida, incluindo casos inválidos/exceções/bordas>.
```

Este registro tem dois propósitos:

1. **Te força a articular** o entendimento. Articular expõe lacunas — você descobre que "achava que sabia" mas não sabia.
2. **Deixa rastro** para quem ler depois (você mesmo amanhã, validador, PO) entender o que você sabia quando começou. Se algo der errado, esse registro mostra se foi má execução ou má interpretação na origem.

Se a primeira entrada acima for difícil de escrever, **você ainda não entendeu o suficiente**.

---

## Quando você "acha" que entendeu

Sinais de que ainda **não** entendeu o bastante para começar:

- Você está se mexendo para "começar" sem ter formulado o plano.
- Você sente que vai "ir descobrindo no caminho".
- Você não consegue explicar a regra de negócio sem olhar a spec.
- Você não consegue listar **3 casos de teste** que pretende escrever.
- Você está pulando seções da estória pensando "isso não importa pra mim".
- Você abriu o editor antes de fechar a leitura.

**Pare e leia de novo.** Custa minutos; pular custa horas.

---

## Quando você LEU e o documento está incompleto ou contraditório

Acontece. Spec antiga, ADR genérica demais, estória do PO com ambiguidade real. **Não tente compensar isso codando:**

- Registre o problema em "Notas do agente" com clareza.
- Marque `status: blocked` com tag apropriada (`[ESCALONAMENTO-PO]` ou `[ESCALONAMENTO-ARQUITETO]`).
- Espere clarificação.
- **Não invente.** Não escolha "a opção mais provável". Não "siga o que parece fazer mais sentido".

O custo de esperar 1 dia é incomparavelmente menor que o custo de fazer errado e retrabalhar — frequentemente com efeito cascata em outras estórias.

---

## Padrões e convenções não documentadas

Você vai encontrar padrões no código que não estão escritos em lugar nenhum mas claramente são convenção. **Respeite-os:**

- Naming de testes, organização de pastas, formato de mensagem de commit, etc.
- Quando em dúvida, faça igual ao código adjacente.
- Quando identificar um padrão importante e recorrente que não está documentado, **considere se vale virar IDR** (decisão de convenção interna) — isso ajuda os próximos agentes a respeitar consistência sem ter que arqueologizar o código.

---

## Documentação externa (libs, plataformas, APIs)

Quando você usa uma lib ou plataforma, **leia a documentação oficial dela** antes de copiar exemplo de Stack Overflow ou blog. Especificamente:

- Versão que o projeto está usando — a doc é dessa versão?
- Práticas oficialmente recomendadas vs. anti-padrões marcados ("don't do this").
- Pegadinhas conhecidas. Boa parte das libs tem seção "common pitfalls", "gotchas", ou "FAQ".
- Migrations entre versões — se você está em versão antiga, exemplos novos podem não se aplicar.

Stack Overflow / blogs são úteis para **ideia inicial**. Decisão final é baseada na **doc oficial da versão certa**.

---

## Caso especial: retomada de estória `blocked` ou `in_progress` por outro

Se você assume uma estória que já estava em andamento (outro agente, ou você em outra sessão), **leia "Notas do agente" inteiras** antes de qualquer outra coisa:

- O que foi feito até agora?
- Que decisões locais foram tomadas?
- Por que estava `blocked`?
- O bloqueio foi resolvido entre lá e agora? Como?

Pular este passo é receita garantida para refazer trabalho ou tomar decisão conflitante.

---

## Caso especial: primeira estória sua no projeto

Se essa é sua **primeira estória do DEFOnline** (você é um agente recém-chegado ou é uma sessão nova com contexto zero), você precisa de uma **leitura mais larga** antes da estória específica:

1. **`AGENTS.md` na raiz do projeto** — visão geral do projeto e ponteiros para skills.
2. **`defonline-docs/skills/README.md`** — entenda os 4 papéis e como você se encaixa.
3. **Sua skill (Programador) e todas as references dela** — você precisa internalizar:
   - `reading-discipline.md` (este)
   - `coding-principles.md`
   - `testing-discipline.md`
   - `library-discipline.md`
   - `done-checklist.md`
   - `security-discipline.md`
   - `database-discipline.md`
   - `observability-discipline.md`
   - `error-handling.md`
   - `pr-discipline.md`
4. **Skill do PO e references aplicáveis** — `agent-task-format.md` (protocolo) e `quality-standards.md` (padrões transversais) são leitura obrigatória.
5. **Princípios arquiteturais do projeto** — `defonline-docs/skills/arquiteto/references/architecture-principles.md`. Você precisa entender o "por quê" das ADRs para não propor coisas que contrariam o estilo do projeto.
6. **ADRs vigentes** em `defonline-docs/project-state/decisions/adr/` — pelo menos os títulos e estados. Você não precisa decorar cada uma, mas saber o que existe.
7. **Especificação funcional** em `defonline-docs/especificacao/V2/especificacao-funcional.md` — leitura panorâmica para entender o domínio (Diagnóstico Econômico-Financeiro para MPE).
8. **Estrutura do código** — depois que o EPIC-000 (Foundation) existir, percorra a estrutura de pastas para entender a organização.

Esse onboarding custa uma sessão. Faça **uma vez**, não toda estória.

**Heurística**: se você consegue, ao fim dessa leitura, explicar em 5 minutos:
- O que o DEFOnline faz para o usuário.
- Quais os 4 papéis e suas fronteiras.
- Os 6 princípios arquiteturais centrais.
- O que é PDR/ADR/IDR e quem cria cada um.
- O que você nunca faz como Programador.

… você está pronto para a primeira estória.

---

## Resumo operacional

Ciclo de leitura, **toda vez** que você assume uma estória:

1. **Estória inteira**, devagar.
2. **Documentos referenciados** (spec, ADRs, PDRs, IDRs aplicáveis).
3. **Skills e referências** (relembre o que é exigido).
4. **Código existente** do módulo a ser tocado.
5. **Checkpoint de entendimento** (6 perguntas acima).
6. **Ambiguidades** → registrar, escalar, esperar.
7. **Plano em "Notas do agente"** com lista de testes que vai escrever.
8. **Só agora, codar.**

> **Velocidade real vem de ter entendido o que fazer. Pressa para codar é fonte de retrabalho.**
