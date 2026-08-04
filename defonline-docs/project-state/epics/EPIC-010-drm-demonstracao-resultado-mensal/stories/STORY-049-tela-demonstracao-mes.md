---
story_id: STORY-049
slug: tela-demonstracao-mes
title: "Tela da Demonstração do Mês — valor nominal e percentual em cada linha"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: M
checkpoint: CP1
---

# STORY-049 — Tela da Demonstração do Mês

## Contexto

Fecha o **Checkpoint 1**: com esta estória, o caminho feliz do módulo está vivo em homologação — o usuário lança um mês e vê se o negócio deu lucro. Tudo o que vem depois melhora um produto que já existe.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: spec da Tela 2 (STORY-046); `epics/EPIC-010-*/design/estrutura-drm.md`; a tela de relatório de Diagnóstico já em produção — mesma linguagem visual, mesmo padrão mobile-first, mesmos componentes.

## O quê

Renderizar a Demonstração de Resultado Mensal produzida pelo motor, com valor nominal e percentual em cada linha, hierarquia visual entre níveis, e todos os estados especificados.

## Por quê

Motor sem tela é invisível. E é aqui que o pedido do Key User — valor nominal junto ao indicador — se materializa.

## Critérios de aceite

- [ ] **CA-1 (Rota e escopo):** rota autenticada para uma competência de uma Empresa Analisada do usuário. Cross-tenant e competência inexistente retornam 404 silente (IDR-009).
- [ ] **CA-2 (Linhas e hierarquia):** todas as linhas de `design/estrutura-drm.md` §1 renderizadas na ordem canônica, com distinção visual entre totalizadores de nível 1 e subtotais de nível 2. Subtotais de grupo em modo agregado não são exibidos como itens.
- [ ] **CA-3 (Nominal e percentual):** cada linha exibe valor em reais formatado no padrão BR **e** percentual sobre a receita. Nenhuma linha exibe só um dos dois.
- [ ] **CA-3b (Troca de modo):** o usuário alterna entre os modos de pró-labore (`PDR-004`, decisão B) e a demonstração é reexibida imediatamente, incluindo rótulos, número de linhas e ordem. A troca **não recalcula nem migra nada** e vale para toda a série. O modo vigente fica visível junto à demonstração.
- [ ] **CA-3c (Linhas não operacionais):** L7b exibida quando há valor, **omitida quando `D41` e `D42` são zero**.
- [ ] **CA-4 (Destaque dos três números):** quanto vendeu, quanto sobrou antes do pró-labore e quanto sobrou depois são localizáveis sem ler a tabela inteira, conforme o spec.
- [ ] **CA-5 (Sem faturamento):** percentuais exibidos como indisponíveis com a mensagem semântica; valores nominais presentes; a tela não parece quebrada nem culpa o usuário.
- [ ] **CA-6 (Prejuízo):** resultado negativo é exibido com clareza e neutralidade — sinalizado, não dramatizado.
- [ ] **CA-7 (Alertas aceitos):** alertas que o usuário aceitou no lançamento aparecem como nota de contexto na demonstração, para que o número seja lido junto com a ressalva.
- [ ] **CA-8 (Snapshot, não recálculo):** a tela lê o resultado persistido. Não invoca o motor. Rodapé exibe versão do motor e data de geração, refletindo o snapshot e não a configuração atual.
- [ ] **CA-9 (Mobile-first):** tabela densa em desktop, cards empilhados em mobile, sem scroll horizontal em 360×800.
- [ ] **CA-10 (Acessibilidade AA):** semântica de tabela com cabeçalhos associados, contraste ≥ 4.5:1, navegação por teclado, nenhum estado comunicado só por cor.
- [ ] **CA-11 (Navegação):** breadcrumb coerente com o app shell e caminho de volta para a empresa e para o lançamento.
- [ ] **CA-12 (Testes):** feature cobrindo render dos três setores, **os dois modos de pró-labore**, mês sem faturamento, mês com prejuízo, modos agregado e detalhado, linhas não operacionais presentes e omitidas, cross-tenant; E2E percorrendo lançamento → demonstração.
- [ ] **CA-13 (Cobertura):** ≥ 80%.

## Fora de escopo

- Faróis e leitura em prosa (STORY-054) — reserve o espaço conforme o spec, sem renderizar.
- Comparativos (STORY-051), exportação (STORY-055), retificação (STORY-050).

## Padrões de qualidade exigidos

`defonline-docs/skills/po/references/quality-standards.md`.

## Dependências

- **Bloqueada por:** STORY-046, STORY-047, STORY-048.
- **Bloqueia:** STORY-051, STORY-054, STORY-055.

## Decisões já tomadas (não as reabra)

- `design/estrutura-drm.md` (ordem e conteúdo das linhas); spec da STORY-046 (layout); IDR-008 (tokens, sem hex literal); IDR-009; padrão snapshot.

## Definição de Pronto

- [ ] CA-1 a CA-13 passam.
- [ ] **Checkpoint 1 demonstrado em homologação:** lançar um mês e ver a demonstração, nos três setores.
- [ ] Pipeline verde; `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Avise o PO em `in_review` — o PO grava a demo do Checkpoint 1.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### IDRs criados
### Cobertura final
