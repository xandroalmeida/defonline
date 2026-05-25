---
story_id: STORY-030
slug: motor-completar-14-indicadores
title: Motor — completar os 7 indicadores restantes (14 no total) + ajuste fino do farol Indústria
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: draft
owner_agent: claude-programador
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: L
---

# STORY-030 — Motor: completar 14 indicadores

> Estende STORY-028 com os 7 indicadores que faltam para fechar o conjunto canônico da spec §4.5. Não muda arquitetura — adiciona classes seguindo o mesmo padrão.

## Contexto

V1 (STORY-028) entregou 7 essenciais. Esta estória completa o conjunto canônico de 14 da spec §4.5: **Margem EBITDA, Despesas Financeiras / EBITDA, Fontes de Recursos (PC/PL), Giro do Ativo, PME, Inadimplência de Clientes, Ciclo Operacional**. Mais ajuste fino das faixas de farol para Indústria conforme §4.5 — algumas faixas só fazem sentido vistas em conjunto (ex.: ciclo operacional × ciclo financeiro).

## O quê

1. **7 novas classes** em `app/Domain/Motor/Indicadores/`, seguindo a interface `Indicador` da STORY-028.
2. **Fórmulas exatas** conforme §4.5; tratamento de casos extremos conforme catálogo da STORY-026.
3. **Faixas de farol** atualizadas em `config/motor/faroes-industria.php` cobrindo agora 13 indicadores com farol + 1 sem (NCG abs).
4. **Fixtures**: ≥ 10 casos por indicador novo (mais 70 casos no total).
5. **Cobertura ≥ 98%** mantida no pacote `app/Domain/Motor/`.
6. **Bump do `motor_version`** para `"1.1.0"` (minor — novos indicadores, sem mudar comportamento dos 7 existentes).
7. **Migration de dados:** diagnósticos antigos em `motor_version = "1.0.0"` continuam reproduzíveis (snapshot preservado); novos diagnósticos passam a calcular 14.

## Critérios de aceite

- [ ] **CA-1:** 7 classes novas, padrão STORY-028.
- [ ] **CA-2:** Fórmulas batem com §4.5 (revisão do PO em par com Arquiteto).
- [ ] **CA-3:** Faixas de farol revisadas para os 7 novos + ajuste fino dos 7 antigos se a leitura do Anexo F sinalizar inconsistência (registrar em decisão).
- [ ] **CA-4:** ≥ 10 casos por indicador novo. Total ≥ 140 casos no pacote motor.
- [ ] **CA-5:** Cobertura ≥ 98% mantida.
- [ ] **CA-6:** `motor_version` bumpa para `"1.1.0"`. Golden hash da fixture canônica muda (esperado); novo hash registrado.
- [ ] **CA-7:** Diagnósticos antigos (`motor_version = 1.0.0`) continuam reproduzíveis em `/diagnosticos/{id}` — snapshot `indicadores_calculados` é a fonte da verdade, não recalcula.
- [ ] **CA-8 (latência):** p95 do motor com 14 indicadores ≤ 1s (parte do budget total ≤ 3s).
- [ ] **CA-9 (relatório):** STORY-029 já lê dinamicamente os indicadores do snapshot — sem mudança de UI nesta estória? Verificar; se a tabela tem header hardcoded para 7, ajustar.

## Fora de escopo

- Resumo Executivo (STORY-031).
- Matriz DEZ/2025 (STORY-032).
- Recalcular diagnósticos antigos (regra: não fazemos).

## Dependências

- **Bloqueada por:** STORY-028.
- **Bloqueia:** STORY-031 (Resumo lê todos os 14), STORY-032 (matriz precisa dos 14).

## Decisões já tomadas

- Bump de `motor_version` para `1.1.0` (minor).
- Diagnósticos antigos preservados via snapshot.
- Faixas de farol em config externa.

## DoD

CA-1 a CA-9 + pre-push verde + pipeline verde + tag `rc.W25S2.1`. `index.json` atualizado.

## Protocolo do agente

Padrão. Avisar PO em `in_review`.

## Notas do agente

*(A preencher.)*
