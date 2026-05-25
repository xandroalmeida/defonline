---
story_id: STORY-034
slug: validacoes-cruzadas-dre-balanco
title: Validações cruzadas DRE × Balanço + mensagens de erro acionáveis (§6.6)
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: draft
owner_agent: claude-programador
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: M
---

# STORY-034 — Validações cruzadas DRE × Balanço

> Detecta inconsistências entre blocos do quiz (não dentro de um campo só) e mostra alerta **não-bloqueante** com mensagem acionável.

## Contexto

A epic.md lista como entregável: *"Validações cruzadas DRE × Balanço e mensagens de erro acionáveis quando Roberto digita dado inconsistente."* A spec §6.6 ainda tem decisões abertas sobre o conjunto exato de regras — o PO consolida no Dia 1 da Semana 4 com o Arquiteto e fecha lista canônica.

**Default mínimo** (regras explicitamente citadas em material existente):

1. Despesas financeiras anualizadas > 2× dívidas (Q16 × 12 > Q06 × 2) — provável erro de digitação no Q16.
2. Custos totais anualizados > vendas anualizadas (Q14 + Q15 × 12 > Q09 × 12) — provável erro em Q14/Q15.
3. Passivo > Ativo (Q06 + Q07 > Q02 + Q03 + Q04) — situação patrimonial inviável; pode ser real, mas vale alertar.

## O quê

1. **Classe `app/Domain/Quiz/ValidacoesCruzadas.php`** com método `validar(QuizPayload $p): array<Alerta>`. Cada `Alerta` tem `regra`, `severidade` (`warning`), `mensagem_acionavel`, `campos_envolvidos`.
2. **Trigger:** ao avançar do Bloco 3 (Balanço) para o Bloco 4 (Contexto), valida. Se houver alertas, mostra modal/banner: "Detectamos inconsistências. Você quer corrigir agora?" com 2 botões: `Corrigir` (volta ao campo) e `Continuar mesmo assim`.
3. **Não-bloqueante:** Roberto pode continuar. Alertas registrados no `quiz_payload.alertas_aceitos[]` para auditoria.
4. **Mensagens acionáveis:** cada alerta linka direto para o campo suspeito. Exemplo: "Suas despesas financeiras mensais (R$ X) são muito altas comparadas às dívidas (R$ Y). Verifique o valor de despesas financeiras." + botão `Ir para Despesas Financeiras`.
5. **Regras em config externa** (`config/quiz/validacoes-cruzadas.php`) — facilita ampliação futura sem mexer em código.

## Critérios de aceite

- [ ] **CA-1 (lista canônica):** PO entrega lista fechada de regras no Dia 1 da Semana 4. Default mínimo: 3 regras acima.
- [ ] **CA-2:** Classe `ValidacoesCruzadas` implementa cada regra; ≥ 3 testes por regra (caso disparado, caso não-disparado, edge case).
- [ ] **CA-3 (não-bloqueante):** Roberto pode continuar após ver alerta. Decisão registrada em `alertas_aceitos`.
- [ ] **CA-4 (acionável):** cada mensagem linka para o campo suspeito.
- [ ] **CA-5 (UX):** modal ou banner — escolha do programador. Preferência PO: banner expansível inline (menos disruptivo).
- [ ] **CA-6 (config externa):** regras em config PHP — adicionar regra nova é editar config + adicionar teste.
- [ ] **CA-7 (testes E2E):** Dusk — preencher quiz com inconsistência conhecida + ver alerta + clicar "Continuar" + ver diagnóstico gerado normalmente.
- [ ] **CA-8 (cobertura):** ≥ 80% no pacote de quiz.

## Fora de escopo

- Validações dentro de um único campo (faixa numérica) — já em STORY-027.
- Validações via IA — roadmap §2.3.
- Bloqueio rígido — decisão explícita: alertas são **avisos**, não impeditivos.

## Dependências

- **Bloqueada por:** STORY-027 (quiz existe), STORY-030 (balanço completo definido).
- **Bloqueia:** nada da V5.

## Decisões já tomadas

- Não-bloqueante (alertas, não erros).
- Regras em config externa.
- Lista canônica fechada no início da Semana 4 — PO + Arquiteto.

## DoD

CA-1 a CA-8 + tag `rc.W25S4.1`. `index.json` atualizado.

## Protocolo do agente

Padrão.

## Notas do agente

*(A preencher.)*
