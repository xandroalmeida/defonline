# Workflow ágil do PO

Este é o método. Não é dogma — é o que faz o produto andar com qualidade e visibilidade.

## A unidade de planejamento: a onda

Uma **onda** (`WAVE-YYYY-NN`) é um ciclo de planejamento de algumas semanas a 2–3 meses, com um objetivo de negócio único. Você planeja **uma** onda em detalhe e mantém um rascunho da próxima. Nunca planeja o projeto inteiro de uma vez — você não tem informação suficiente.

Estrutura mental:

```
ONDA atual (planejada em detalhe)
 ├── EPIC 1 — estórias detalhadas
 ├── EPIC 2 — estórias detalhadas
 └── EPIC 3 — estórias detalhadas
ONDA seguinte (rascunho)
 ├── EPIC A — só título e outcome
 └── EPIC B — só título e outcome
ONDAS futuras
 └── (não existe ainda — você não sabe e tudo bem)
```

No fim de cada onda você faz uma retrospectiva curta (qual hipótese se confirmou? o que aprendemos?) e replaneja a próxima.

### Hierarquia colapsável no MVP cedo

Onda + sprint + épico + estória é a hierarquia completa, e ela escala bem. **No MVP muito cedo** (time de 1-2 pessoas, primeiros meses, foco em destravar fundação), é aceitável **colapsar onda e sprint** em um único ciclo, ou operar com **épico + estória** apenas — formalismo cresce com o time e com a duração do projeto.

Sinal de que vale colapsar: você está abrindo onda com 1 épico, e sprint com 2-3 estórias do mesmo épico. Aí é mais simples ter "ciclo único". Quando o time/escopo cresce, formaliza.

**O que NÃO colapsa, em qualquer fase:**

- `index.json` e estórias formais — esses são o estado do projeto, não dependem do tamanho.
- Definition of Done — qualidade não é negociável (princípio do PO).
- Estado das estórias (`ready`, `in_progress`, `done`) — disciplina de execução.

## Épicos

Um **épico** entrega valor visível ao usuário final. Tem:

- Um problema do usuário no centro.
- Um outcome observável.
- Uma métrica de sucesso (cruzando com `measurement-and-learning.md`).
- Algo que estará deployado em homologação ao final.
- Uma estória de validação como última etapa.

Tamanho típico: **1–3 sprints**. Se ficar maior, divide. Se ficar menor que 1 sprint, talvez seja uma estória solta, não um épico.

### Quantidade típica de épicos por onda

Tipicamente **3–7 épicos por onda** — número que mantém escopo focado mas dá ao usuário final entregas observáveis sucessivas. **Exceções legítimas:**

- **Primeira onda** pode ter **menos** (2-3 épicos), pelo viés de fundação — EPIC-000 (Foundation) + 1-2 features iniciais já é o suficiente para validar deploy ponta a ponta.
- **Onda focada de hotfix/débito**: pode ter 1-2 épicos densos.
- **Onda de exploração** após descoberta importante: pode ter muitos pequenos para validar várias direções.

A regra de 3-7 é diretriz, não imperativo cego — mantenha foco da onda como critério principal.

## Estórias verticalmente fatiadas (vertical slicing)

Esta é a regra que mais separa um plano que funciona de um que trava.

**Errado (horizontal):**
- STORY-001: criar todas as tabelas do banco
- STORY-002: criar toda a API
- STORY-003: criar toda a UI

Resultado: nada visível até a STORY-003. Bug encontrado lá vira retrabalho gigante. Usuário não viu nada por semanas.

**Certo (vertical):**
- STORY-001: cadastro de empresa — UI + API + banco — só com nome e CNPJ — visível em homologação
- STORY-002: cadastro de empresa — adicionar endereço — UI + API + banco — visível
- STORY-003: cadastro de empresa — validação de CNPJ contra Receita — visível

Cada estória atravessa o stack inteiro, entrega um pedaço pequeno mas observável, e fica deployada em homologação ao final.

### Exceções legítimas a vertical slicing

Vertical slicing é o **default**. Mas alguns trabalhos são **horizontais por natureza** e forçar verticalidade neles cria atrito artificial:

- **Setup de infraestrutura inicial** (provisionar ambientes, configurar pipeline) — não pertence a fluxo de usuário específico.
- **Observabilidade transversal** (configurar logger, métricas, tracing).
- **Sistema de autenticação base** (antes de existir feature que usa).
- **Audit log do sistema** (mecanismo, antes do primeiro consumidor).
- **Refatoração estrutural** (mover módulo, renomear convenção em toda a base).

Para essas, horizontalidade é exceção justificada. O que importa:

- **O épico que contém ainda entrega algo observável no fim** — a primeira feature que **usa** a infra. Sem isso, vira épico de "preparação eterna" sem entregar valor.
- **A estória horizontal é nomeada como tal** (`type: enablement` no frontmatter — veja `story-craft.md`).
- **A justificativa para horizontalidade está escrita** na estória ("Esta estória é horizontal porque [X]. Destrava STORY-YYY, STORY-ZZZ.").

**Sinal de abuso**: tudo virou enablement, nada virou feature visível ao usuário. Aí o problema é outro — provavelmente o PO está postergando entregar valor. Reveja.

## Sprints

Um **sprint** é um intervalo fixo (sugiro 1 ou 2 semanas) em que um conjunto definido de estórias é executado. Ele:

- Pertence a uma onda.
- Tem um goal claro (1 frase).
- Tem estórias selecionadas no início e congeladas até o fim (mudança vira sprint seguinte).
- Termina com revisão (o que foi feito) e ajuste (o que muda no próximo).

## Estados (status)

Use estes valores no frontmatter dos arquivos e no `index.json`:

### Estórias

| status | significa |
|---|---|
| `draft` | rascunho do PO, ainda não pronta para o agente |
| `ready` | pronta para um agente assumir |
| `in_progress` | um agente está executando |
| `blocked` | parou por bloqueio externo ou dúvida que precisa do PO/Arquiteto |
| `in_review` | agente terminou, aguardando validação humana / merge / validador |
| `done` | mergeada, deployada em homologação, critérios passam |
| `abandoned` | descartada com justificativa registrada |

### Épicos

| status | significa |
|---|---|
| `draft` | PO ainda escrevendo |
| `ready` | estórias detalhadas, pronto para começar |
| `in_progress` | tem pelo menos 1 estória em execução |
| `in_review` | todas as estórias `done`, esperando relatório do validador |
| `done` | validador aprovou |
| `abandoned` | cancelado |

## Cadência típica

- **Diário:** o PO consulta `index.json`, vê o que está `in_progress` e `blocked`, desbloqueia o que pode.
- **Semanal (fim de sprint):** PO gera status report, fecha sprint, abre próximo (veja `sprint-mechanics.md`).
- **Fim de épico:** validação → relatório → retro curta → métricas observadas (veja `measurement-and-learning.md`) → próximo épico.
- **Fim de onda:** retrospectiva maior → detalhamento da próxima onda → atualização do roadmap (próxima seção).

## Manutenção do roadmap

O roadmap vive em `defonline-docs/project-state/roadmap/`:

- `current-wave.md` — onda em execução, **detalhada** (épicos, sequência, justificativa de ordem).
- `next-wave.md` — **rascunho** da próxima onda (só títulos de épicos e outcomes esperados).

### Cadência de atualização

| Evento | Atualização |
|---|---|
| Fim de cada épico | Marcar épico concluído em `current-wave.md`; atualizar próximo épico ativo |
| Fim de cada onda | Mover `next-wave.md` para `current-wave.md`; escrever novo `next-wave.md` rascunho |
| Aprendizado significativo (métrica do épico, feedback de usuário) | Atualizar `next-wave.md` se a hipótese mudar |
| Decisão de produto (PDR) que afeta direção | Refletir em ambos os arquivos |

### O que NÃO entra no roadmap

- Estórias detalhadas — essas vivem em `epics/EPIC-XXX-*/stories/`.
- Datas exatas (a menos que haja compromisso real com cliente) — datas viram pressão artificial.
- Detalhamento de ondas futuras além da próxima — você não tem informação para isso.

## Engajamento proativo com o Arquiteto

O fluxo padrão é reativo: se uma estória precisa de decisão arquitetural, abre spike e bloqueia até resolver. **Para épicos importantes, anteciparse vale ouro.**

### Cadência sugerida

**Antes de detalhar estórias de um épico:**

1. Releia ADRs vigentes que se aplicam (`defonline-docs/project-state/decisions/adr/`).
2. Identifique se há **decisões arquiteturais necessárias** que ainda não existem.
3. Se sim, abra **estória de spike** para o Arquiteto antes (não no meio) das estórias de implementação.
4. Aguarde a ADR ser aceita pelo Alexandro antes de detalhar estórias dependentes.

**Conversa breve no chat ajuda mais que estória formal** quando a dúvida é direcional:

> "Estou planejando o EPIC-007 (Importação de Planilha). Vejo possível dependência arquitetural sobre processamento assíncrono — temos ADR cobrindo, ou precisamos abrir spike antes?"

O Arquiteto responde, e você sabe se precisa de spike ou pode prosseguir.

### Sinais de que engajamento veio tarde

- Estória entrou em `blocked` esperando ADR.
- Programador está parado esperando decisão.
- Sprint perde estórias por falta de spike planejado.

Esses sinais indicam que o próximo épico merece **mais cedo** o checkpoint com o Arquiteto.

## Pré-épico zero: a fundação

## Pré-épico zero: a fundação

Antes do primeiro épico de funcionalidade, há trabalho de fundação que destrava todo o resto:

- ADRs essenciais (stack, arquitetura macro, hospedagem).
- Repositório de código criado.
- Pipeline CI/CD básico funcionando ("hello world" deployado em homologação).
- Ambiente de homologação acessível por URL.

Trate este pré-épico como **EPIC-000 — Foundation** ou similar. Ele é curto, mas é o que torna todo o resto possível. O critério de "pronto" dele é: "merge na main faz deploy automático de uma página viva em homologação". Sem isso, os demais épicos ficam fingindo.

## Quando um épico termina

Um épico só está `done` quando:

1. Todas as estórias estão `done`.
2. O relatório do validador (`validation/report.md`) está com status `approved`.
3. O entregável listado em `epic.md` está acessível em homologação ou produção.
4. O `index.json` foi atualizado.

Se 1–3 forem true mas 4 não, ainda não está pronto — atualize o índice.

## Quando reabrir uma estória `done`

Em princípio, não reabra — abra uma nova estória de correção e referencie a antiga. Reabrir confunde o histórico.

## Vertical slicing aplicado: como reconhecer uma estória mal fatiada

Sinais de problema:

- "Só de UI" ou "só de backend".
- Critério de aceite menciona "estrutura de tabela" ou "endpoint exposto", mas não fala em comportamento observável.
- Não dá pra demonstrar em homologação ao final.
- Estimativa é "L" mas o agente não consegue listar testes E2E pra ela — provavelmente está atravessando demais ou de menos.

Quando reconhecer, refatore a estória antes de despachar para o agente.
