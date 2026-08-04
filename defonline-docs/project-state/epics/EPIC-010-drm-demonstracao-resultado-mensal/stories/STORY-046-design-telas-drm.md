---
story_id: STORY-046
slug: design-telas-drm
title: "Specs de tela do módulo DRM — lançamento, demonstração do mês e série histórica"
epic_id: EPIC-010
type: implementation
target_role: designer
requires_design: true
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: L
---

# STORY-046 — Specs de tela do módulo DRM

> **Para o agente que vai executar:** você é o **Designer**. Esta estória roda em paralelo à STORY-045 (spike do Arquiteto) e precisa estar `ready` antes da STORY-047 começar.

## Contexto

Este módulo tem um problema de desenho maior que o problema de código. São até 36 campos monetários por mês, doze vezes por ano, preenchidos por alguém que não gosta de planilha e que está fazendo isso no celular, à noite, depois de fechar a loja. **Se a tela de lançamento for uma lista de 36 campos, o módulo morre no segundo mês** — e a métrica primária do épico (2º lançamento em 45 dias) é justamente o detector desse fracasso.

O PDR-003 já fixou os três mecanismos anti-atrito. Seu trabalho é fazê-los funcionar visualmente.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` (integral); `epics/EPIC-010-*/design/estrutura-drm.md`; `decisions/pdr/PDR-003-*.md`; `especificacao/V2/design-system.md`; `decisions/idr/IDR-008-*` (tokens)
- Referência viva: telas do Quiz e do relatório de Diagnóstico já em produção — reaproveite padrões, não invente dialeto novo.

## O quê

Produzir **três specs de tela** usando `defonline-docs/skills/designer/templates/screen-spec.md`, mais os DDRs que as decisões exigirem.

### Tela 1 — Lançamento mensal

O centro do problema. Precisa resolver, visualmente:

- **Escolha de modo por grupo** (Anexo K §K.5). Como o usuário entende, em 3 segundos, que pode informar só o total de "despesas administrativas" em vez de 21 itens — e o que ele perde ao fazer isso. A escolha não pode parecer um atalho preguiçoso; é uma opção legítima.
- **Pré-preenchimento com o mês anterior.** Como um campo "repetido do mês passado" se distingue de um campo confirmado, sem poluir a tela com badges em 30 campos. Como o usuário confirma tudo de uma vez.
- **Progresso e retomada.** O usuário vai sair no meio. Onde ele estava, quanto falta, como volta.
- **Adaptação por setor** — Serviços vê menos campos, e isso deve parecer natural, não incompleto.
- **Alertas e bloqueios** (§K.6), diferenciando o que impede o fechamento do que só pede confirmação.

### Tela 2 — Demonstração do Mês

Saída principal. Precisa carregar simultaneamente **valor nominal e percentual sobre a receita** — o pedido literal do Key User — sem virar planilha.

- Hierarquia visual entre linhas totalizadoras (L1, L3, L5, L7, L9, L11) e subtotais (L6a, L6b, L8a, L8b). Ver `design/estrutura-drm.md` §1.
- Onde os três números que importam ficam: **quanto vendeu**, **quanto sobrou antes da retirada**, **quanto sobrou depois**. O usuário deve encontrá-los sem ler a tabela inteira.
- Estado de indisponibilidade (mês sem faturamento): percentuais ausentes, valores nominais presentes, motivo explicado sem parecer erro do sistema.
- Estado de prejuízo. É informação, não falha — e não pode parecer que o app está brigando com o usuário.
- Espaço reservado para farol e leitura (STORY-054), sem que a tela pareça incompleta antes deles.
- Mobile-first: tabela densa em desktop, cards empilhados em mobile — mesmo padrão do relatório de Diagnóstico.

### Tela 3 — Série histórica

- Lista de competências, com estados distintos: lançada, retificada, **não lançada** (≠ sem movimento), rascunho em aberto.
- Variação vs. mês anterior e vs. média móvel, incluindo os estados de indisponibilidade (`design/estrutura-drm.md` §6).
- Marcação de mês atípico (alerta A4) legível como explicação, não como advertência.
- Entrada para lançar o mês faltante e para retificar um mês fechado.
- Sem gráfico obrigatório no MVP. Se propuser um, justifique o ganho de leitura no spec.

## Por quê

Conecta com as duas primeiras métricas do épico: recorrência (2º lançamento) e atrito (≥70% de conclusão, ≤5 min do 2º mês em diante). Nenhuma das duas é ganha no backend.

## Critérios de aceite

- [ ] **CA-1:** Três specs publicados em `epics/EPIC-010-*/design/`, seguindo o template, com mobile e desktop.
- [ ] **CA-2:** O spec de lançamento apresenta **solução explícita para cada um dos três mecanismos anti-atrito** do PDR-003, com o raciocínio de por que aquela solução reduz esforço percebido.
- [ ] **CA-3:** A Demonstração do Mês exibe nominal e percentual em cada linha, com hierarquia visual clara entre os três níveis.
- [ ] **CA-4:** Todos os estados de indisponibilidade e alerta do Anexo K §K.6 e §K.7 estão especificados — nenhum estado fica "a definir na implementação".
- [ ] **CA-5:** Só componentes e tokens do design system existente. Componente novo exige justificativa no spec e entra no DS. **Nenhum hex literal** (IDR-008).
- [ ] **CA-6:** Acessibilidade AA: contraste ≥ 4.5:1, alvo de toque ≥ 44×44px, farol nunca só por cor, navegação por teclado no formulário longo, ordem de tabulação coerente.
- [ ] **CA-7:** DDR registrado para cada decisão de design com efeito além destas três telas.
- [ ] **CA-8:** Specs revisados com o PO antes de `ready`.

## Fora de escopo

- Implementar qualquer coisa.
- Telas de exportação (STORY-055) e de retificação além do ponto de entrada (STORY-050).
- Redação final de rótulos e textos de ajuda — é o pacote editorial da EBC (STORY-052). Use texto provisório e marque como tal.

## Padrões de qualidade exigidos

`defonline-docs/skills/designer/SKILL.md` e `references/`. O entregável é o spec, não código.

## Dependências

- **Bloqueada por:** nada. Roda em paralelo à STORY-045.
- **Bloqueia:** STORY-047, STORY-049, STORY-051.

## Decisões já tomadas (não as reabra)

- PDR-003, decisões 1, 4, 5 e 6.
- Anexo K — quais campos existem e quando aparecem.
- `design/estrutura-drm.md` — quais linhas a demonstração tem e em que ordem.
- IDR-008 — tokens em `tokens.css`.

## Definição de Pronto

- [ ] CA-1 a CA-8 atendidos.
- [ ] `index.json` atualizado (seção `design.screens`).
- [ ] Notas do agente preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### DDRs criados
