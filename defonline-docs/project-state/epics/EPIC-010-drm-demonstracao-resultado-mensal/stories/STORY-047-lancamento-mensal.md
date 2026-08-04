---
story_id: STORY-047
slug: lancamento-mensal
title: "Lançamento mensal — formulário adaptativo por setor, modos agregado/detalhado, rascunho e pré-preenchimento"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: L
---

# STORY-047 — Lançamento mensal

## Contexto

Esta é a estória de maior risco do épico. Não pelo cálculo — que é trivial — mas porque é aqui que o usuário decide se volta no mês seguinte. Os três mecanismos anti-atrito do PDR-003 não são "nice to have": são o produto.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` (integral — é a referência canônica dos campos); a decisão da STORY-045; os specs da STORY-046; `decisions/pdr/PDR-003-*.md`
- Código existente a estudar antes: o componente de Quiz do Diagnóstico e o mecanismo de rascunho. Reaproveite padrões — máscara BRL, validação declarativa, persistência de rascunho, tooltips. **Não crie um segundo dialeto de formulário no produto.**

## O quê

Implementar o fluxo de lançamento mensal do DRM, do ponto de entrada até o lançamento fechado e persistido, pronto para o motor consumir.

## Por quê

Sem entrada não há módulo. E sem entrada de baixo atrito não há segundo mês — que é a métrica primária do épico.

## Critérios de aceite

- [ ] **CA-1 (Ponto de entrada e escopo):** o lançamento acontece sempre no contexto de uma Empresa Analisada do usuário autenticado. Acesso cross-tenant retorna 404 silente (IDR-009). Setor e documento vêm da empresa e **não são perguntados** (Anexo K §K.3.1).
- [ ] **CA-2 (Competência):** campo obrigatório mês/ano, default = mês anterior ao corrente. Competência futura é bloqueada (regra B2). Competência já lançada é bloqueada e oferece abrir ou retificar o lançamento existente (regra B1).
- [ ] **CA-3 (Adaptação por setor):** Comércio não exibe matéria-prima; Serviços não exibe compras de mercadorias nem matéria-prima; o rótulo do grupo de custo muda por setor (CPV / CMV / CSP). Campos suprimidos são gravados como nulos. Ver Anexo K §K.4.
- [ ] **CA-4 (Modo agregado/detalhado):** cada um dos quatro grupos alterna entre total único e detalhamento por item, conforme §K.5 — incluindo o pré-preenchimento do total pela soma ao ir para agregado, a confirmação explícita no caminho destrutivo inverso, e a persistência do modo como preferência da empresa.
- [ ] **CA-5 (Pré-preenchimento):** havendo competência anterior lançada, os campos vêm preenchidos com aqueles valores, visualmente marcados como repetidos até confirmação, com ação de confirmar tudo de uma vez. Sem competência anterior, campos vazios.
- [ ] **CA-6 (Rascunho):** salvo automaticamente a cada bloco concluído e retomável em outra sessão; ao fechar o lançamento, o rascunho é encerrado preservando trilha de auditoria — mesmo tratamento dado ao rascunho do Quiz.
- [ ] **CA-7 (Entrada em reais):** todos os campos monetários com máscara BRL, valor mínimo zero, negativos rejeitados (regra B3). **Nenhum campo pede valor "em mil reais"** (PDR-003, decisão 1).
- [ ] **CA-8 (Regras bloqueantes):** B1 a B4 do Anexo K §K.6 impedem o fechamento, com mensagem específica por regra.
- [ ] **CA-9 (Alertas):** A1, A2, A3 e A5 sinalizam e permitem prosseguir mediante aceite explícito, registrado junto ao lançamento — mesmo padrão do `alertas_aceitos` do Diagnóstico. A4 e A6 dependem de histórico e entram na STORY-051; deixe o ponto de extensão preparado, sem implementá-las.
- [ ] **CA-10 (Persistência):** lançamento fechado é persistido conforme a decisão da STORY-045, incluindo o modo de cada grupo e os alertas aceitos.
- [ ] **CA-11 (Mobile):** fluxo completável em viewport 360×800 sem scroll horizontal, alvo de toque ≥ 44×44px.
- [ ] **CA-12 (Testes):** unitários das regras de validação e da adaptação por setor; feature cobrindo o fluxo completo nos três setores, os dois modos, retomada de rascunho e cada regra bloqueante; E2E em browser percorrendo lançamento completo em pelo menos um setor.
- [ ] **CA-13 (Cobertura):** ≥ 80% no código novo; ≥ 98% no que for regra de negócio.

## Fora de escopo

- Cálculo da demonstração (STORY-048) e sua exibição (STORY-049).
- Retificação (STORY-050); série histórica e alertas A4/A6 (STORY-051).
- Redação final de rótulos e tooltips (STORY-052) — use os textos do Anexo K como provisórios.
- Linhas `[DECIDIR-E]` (D41/D42) enquanto a EBC não decidir.

## Padrões de qualidade exigidos

`defonline-docs/skills/po/references/quality-standards.md`. Sem código não testado. Qualquer setup novo automatizado.

## Dependências

- **Bloqueada por:** STORY-045 (modelo de dados), STORY-046 (specs).
- **Bloqueia:** STORY-048, STORY-049, STORY-050, STORY-051.

## Decisões já tomadas (não as reabra)

- PDR-003 (todas as decisões de escopo); Anexo K; a decisão da STORY-045; ADR-003; IDR-008; IDR-009.

## Liberdade técnica do agente

Você decide estrutura de código, composição de componentes, forma da validação declarativa e desenho dos testes. Você **não** decide: quais campos existem (Anexo K), o modelo de persistência (STORY-045), o layout (STORY-046), nem os critérios de aceite.

Se descobrir que o Anexo K é ambíguo ou incompleto, **pare e registre** — não invente campo nem regra.

## Definição de Pronto

- [ ] CA-1 a CA-13 passam.
- [ ] Pipeline verde; deploy em homologação verificado.
- [ ] IDR registrado se houve decisão de baixo nível com impacto futuro.
- [ ] `index.json` atualizado; notas do agente preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### IDRs criados
### Cobertura final
