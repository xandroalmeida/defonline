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

A epic.md lista como entregável: *"Validações cruzadas DRE × Balanço e mensagens de erro acionáveis quando Roberto digita dado inconsistente."* A **spec V2 §6.6** propõe 3 regras canônicas marcadas como `[DECIDIR]`. **Decisão do PO em 2026-05-25:** adotar as **3 regras da spec §6.6 como conjunto canônico do MVP** — não esperar até a Semana 4 para definir. Regras adicionais sugeridas em execução viram backlog para sprint pós-EPIC-002.

**Lista canônica (idêntica à spec §6.6 — fonte da verdade):**

| Regra | Condição (campos do Anexo A) | Hipótese de erro |
|---|---|---|
| R1 — Despesas financeiras × dívida | `Q16 × 12 > Q06 × 2` | Despesas financeiras anualizadas mais que o dobro da dívida declarada — provável erro de digitação em Q16. |
| R2 — Custos anuais × receita anual | `Q14 × 12 + Q15 × 12 > Q09 × 12` | Custos totais anuais ultrapassam a receita anual — provável erro em Q14/Q15. |
| R3 — Passivo × Ativo | `Q02 + Q03 + Q04 + Q05 < Q06 + Q07` | Passivo total maior que Ativo total — situação patrimonial inviável (PL negativo); pode ser real, mas vale alertar. |

> **Correção 2026-05-25:** a redação original tinha duas fórmulas divergentes da spec — Q14 sem anualização (R2) e ativo sem Q05 (R3). Realinhado à §6.6 da spec funcional V2.

## O quê

1. **Classe `app/Domain/Quiz/ValidacoesCruzadas.php`** com método `validar(QuizPayload $p): array<Alerta>`. Cada `Alerta` tem `regra`, `severidade` (`warning`), `mensagem_acionavel`, `campos_envolvidos`.
2. **Trigger:** ao avançar do Bloco 3 (Balanço) para o Bloco 4 (Contexto), valida. Se houver alertas, mostra modal/banner: "Detectamos inconsistências. Você quer corrigir agora?" com 2 botões: `Corrigir` (volta ao campo) e `Continuar mesmo assim`.
3. **Não-bloqueante:** Roberto pode continuar. Alertas registrados no `quiz_payload.alertas_aceitos[]` para auditoria.
4. **Mensagens acionáveis:** cada alerta linka direto para o campo suspeito. Exemplo: "Suas despesas financeiras mensais (R$ X) são muito altas comparadas às dívidas (R$ Y). Verifique o valor de despesas financeiras." + botão `Ir para Despesas Financeiras`.
5. **Regras em config externa** (`config/quiz/validacoes-cruzadas.php`) — facilita ampliação futura sem mexer em código.

## Critérios de aceite

- [ ] **CA-1 (lista canônica fixada):** R1, R2, R3 (acima) implementadas com fórmulas idênticas à spec §6.6. Regras adicionais sugeridas durante a execução viram backlog — **não entram nesta estória**.
- [ ] **CA-2:** Classe `ValidacoesCruzadas` implementa cada regra; ≥ 3 testes por regra (caso disparado, caso não-disparado, edge case — valor exatamente no limiar).
- [ ] **CA-3 (não-bloqueante + registro do "aceito"):** Roberto pode continuar após ver alerta. A decisão fica registrada em `quiz_payload.alertas_aceitos` (array de `{regra, ocorrido_em, valor_envolvido}`). Como `quiz_payload` é canonicalizado para o `payload_hash` da IDR-010, `alertas_aceitos` **não** entra no canonicalize (não muda hash) — confirmado por teste arquitetural junto com STORY-026.
- [ ] **CA-4 (acionável):** cada mensagem linka direto para o campo suspeito. Textos finais aprovados pelo PO antes do início da implementação (default a usar se PO não revisar em ≤ 1 dia útil — exemplos no item "Textos default" abaixo).
- [ ] **CA-5 (UX):** banner expansível inline ao fim do Bloco 3 (Balanço) antes de avançar para o Bloco 4 (preferência do PO declarada). Botões: `Corrigir agora` (foco no primeiro campo da regra) e `Continuar mesmo assim`.
- [ ] **CA-6 (config externa):** regras em `config/quiz/validacoes-cruzadas.php` — adicionar regra nova = editar config + adicionar teste, sem refactor de classe.
- [ ] **CA-7 (testes E2E):** Dusk — preencher quiz disparando cada uma das 3 regras + ver alerta + clicar "Continuar" + ver diagnóstico gerado normalmente + asserir `alertas_aceitos` no payload.
- [ ] **CA-8 (cobertura):** ≥ 80% no pacote de quiz, ≥ 95% na classe `ValidacoesCruzadas` (hot path simples).

## Textos default das mensagens (a confirmar pelo PO no Dia 1)

- **R1:** *"Suas despesas financeiras mensais (R$ {Q16}) anualizadas representam {Q16×12/Q06:.0%} da dívida declarada (R$ {Q06}). Isso é incomum — verifique o valor de despesas financeiras."* Botão: `Ir para Despesas Financeiras (Q16)`.
- **R2:** *"Os custos totais anuais (R$ {(Q14+Q15)×12}) ultrapassam a receita anual (R$ {Q09×12}). Provavelmente houve erro em custos ou em receita."* Botão: `Ir para Custos (Q14/Q15)`.
- **R3:** *"O passivo total (R$ {Q06+Q07}) é maior que o ativo total (R$ {Q02+Q03+Q04+Q05}), o que indica PL negativo. Se for o caso real, prossiga — o diagnóstico vai sinalizar."* Botão: `Revisar Balanço`.

## Fora de escopo

- Validações dentro de um único campo (faixa numérica) — já em STORY-027.
- Validações via IA — roadmap §2.3.
- Bloqueio rígido — decisão explícita: alertas são **avisos**, não impeditivos.

## Dependências

- **Bloqueada por:** STORY-027 (quiz existe — `done` ✓), STORY-030 (balanço completo — `done` ✓).
- **Bloqueia:** nada da V5.

## Referências cruzadas

- Spec V2 funcional §6.6 (regras canônicas).
- Anexo A do quiz (numeração Q01–Q23).
- IDR-010 §Sub-decisão 3 (`payload_hash` canonicalize — `alertas_aceitos` fica fora).

## Decisões já tomadas

- Não-bloqueante (alertas, não erros).
- Regras em config externa.
- **Lista canônica = §6.6 da spec** (R1/R2/R3 acima) — fechada em 2026-05-25 pelo PO, não esperar Semana 4.
- `alertas_aceitos` vive em `quiz_payload` mas **fora do canonicalize** (não muda `payload_hash` — IDR-010 §Sub-decisão 3).
- UX: banner inline expansível ao fim do Bloco 3, não modal.

## DoD

CA-1 a CA-8 + tag `rc.W25S4.1`. `index.json` atualizado.

## Protocolo do agente

Padrão.

## Notas do agente

*(A preencher.)*
