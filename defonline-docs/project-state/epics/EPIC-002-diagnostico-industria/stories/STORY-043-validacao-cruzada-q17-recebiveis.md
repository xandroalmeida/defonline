---
story_id: STORY-043
slug: validacao-cruzada-q17-recebiveis
title: Validação cruzada R4 — Q17.recebíveis ≥ Q09.venda_mensal (bloqueante)
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
type: implementation
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-06-02
updated_at: 2026-06-02
estimated_session_size: S
related_devolutiva: ".docx EBC §Bloco 4 — 'também deve ser ocultada a informação sobre ser inferior à venda mensal, pois também deve ser uma regra para checagem e não aceitar informação neste campo (recebíveis) de valor inferior ao contido no campo 2 do bloco 2 (vendas – média mensal)'"
---

# STORY-043 — Validação cruzada R4 (Q17.recebíveis ≥ Q09.venda_mensal)

> Nova regra de consistência **bloqueante** entre Q17 (recebíveis — base da capacidade de garantia) e Q09 (venda mensal) do quiz Indústria. Estende a engine declarativa de validações cruzadas da STORY-034.

## Contexto

A EBC pediu que o campo Q17 (recebíveis / base da capacidade de garantia, 3×) **não aceite valor inferior à venda mensal Q09**. A devolutiva original tratava isso como texto auxiliar visível ("Deve ser inferior à venda mensal..."); o PO decidiu **converter em regra de validação**:

- O texto auxiliar é **removido** (já tratado em STORY-042 CA-6).
- A regra vira **R4** declarativa em `config/quiz/validacoes-cruzadas.php`, reaproveitando a engine `App\Domain\Quiz\ValidacoesCruzadas` da STORY-034.
- **Diferente** de R1/R2/R3 (não-bloqueantes), R4 é **bloqueante** (consistência de input, não hipótese de erro estatístico).

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- STORY-034 (engine de validações cruzadas — `done`).
- Spec V2 §6.6 (formato canônico das regras — esta vira **R4** aditiva).

## O quê

Adicionar regra R4 declarativa que impede submissão do quiz quando `Q17.recebíveis < Q09.venda_mensal`, mostrando mensagem acionável que aponta para o campo suspeito.

## Por quê

Captura consistência de input mínima para o motor: `recebíveis` é base da capacidade de garantia (3×); se for menor que vendas mensais, o cálculo perde sentido. Ajusta confiabilidade do diagnóstico sem inflar UI.

## Critérios de aceite

- [ ] **CA-1 (regra declarativa em config):** R4 adicionada em `config/quiz/validacoes-cruzadas.php` no mesmo formato declarativo das R1/R2/R3. Sem closures (config:cache-safe).
- [ ] **CA-2 (bloqueante):** Dado `Q17 < Q09` no submit, então o submit é **impedido** (diferente das R1/R2/R3 que mostram banner "Continuar mesmo assim"); usuário não consegue avançar sem ajustar o valor.
- [ ] **CA-3 (mensagem acionável):** Erro exibido: *"Recebíveis (R$ {Q17}) é menor que a venda mensal média (R$ {Q09}). O valor de recebíveis deve ser pelo menos igual à venda mensal."* Com botão `Ir para Recebíveis (Q17)`.
- [ ] **CA-4 (separação UX):** R4 aparece **antes** do submit (no botão `Calcular diagnóstico`), não como banner inline. R1/R2/R3 mantêm o comportamento atual (banner antes do Bloco 4 → Bloco "Diagnóstico", não-bloqueante).
- [ ] **CA-5 (não-disparado):** Dado `Q17 ≥ Q09`, submit funciona normalmente.
- [ ] **CA-6 (igualdade no limiar):** `Q17 = Q09` passa (validação é `<`, não `≤`).
- [ ] **CA-7 (engine reutilizada):** R4 usa a mesma `ValidacoesCruzadas` da STORY-034 com flag/severidade nova `blocking` (ou similar) — Programador decide naming. Não criar engine paralela.
- [ ] **CA-8 (cobertura):** ≥ 3 testes Pest na regra (disparado / não-disparado / limiar exato); 1 teste arquitetural do `payload_hash` (R4 não muda hash se canonicalizer estiver correto); 1 Dusk submetendo `Q17 < Q09` e vendo bloqueio.
- [ ] **CA-9 (regressão):** R1/R2/R3 continuam não-bloqueantes; smoke E2E passa.

## Fora de escopo

- Outros campos do Q17 (cargo de garantia, etc).
- Reescrita das R1/R2/R3.
- Adicionar mais regras bloqueantes preventivamente — esperar próximas devolutivas.

## Padrões de qualidade exigidos

- **Cobertura ≥ 95% no diff** (regra pequena e crítica).
- **Sem impacto em `payload_hash`** (alertas registrados não entram no canonicalize — IDR-010 §3).

## Dependências

- **Bloqueada por:** STORY-042 (CA-6 — textos auxiliares de Q17 removidos antes; a engine pré-existe).
- **Bloqueia:** nada.

## Decisões já tomadas

- **R4 é bloqueante** (PO 2026-06-02; difere das R1/R2/R3 — consistência de input, não hipótese).
- **Limiar:** `Q17 < Q09` dispara erro; `Q17 = Q09` passa.
- **Engine reutilizada** (não criar paralela).

## Liberdade técnica do agente

- Como representar `severidade: blocking` na config (novo enum / flag).
- Como conectar a regra bloqueante ao submit (Livewire rule customizada / validator pré-submit).
- Como mostrar o erro (modal ou bloco fixo no botão de submit — Programador decide UX).

## Definição de Pronto

- [ ] CA-1..CA-9 passam.
- [ ] Suíte completa Pest verde (777+ testes).
- [ ] CI verde.
- [ ] Deploy em homol verificado.
- [ ] `index.json` atualizado.

## Notas do agente

### Decisões tomadas
- 

### Cobertura final
- 

### Links de evidência
- 
