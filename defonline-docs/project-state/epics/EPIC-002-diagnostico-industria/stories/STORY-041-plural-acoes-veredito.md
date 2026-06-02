---
story_id: STORY-041
slug: plural-acoes-veredito
title: Plural "ações" no texto fixo do veredito em_alerta
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
type: bugfix
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-06-02
updated_at: 2026-06-02
estimated_session_size: XS
related_devolutiva: "Áudio Edvan 2026-05-27 — 'achei excelente essa abertura aí no resumo executivo: sua empresa apresenta indicadores em estado de alerta que demandam ações. Talvez no plural, talvez ficasse melhor.'"
---

# STORY-041 — Plural "ações" no veredito em_alerta

> Edvan aprovou o texto de abertura do veredito `em_alerta` mas sugeriu trocar **"demandam ação"** → **"demandam ações"** (plural). Diff de 1 palavra.

## Contexto

Devolutiva do Edvan (áudio 2026-05-27): "achei excelente essa abertura aí no resumo executivo: 'sua empresa apresenta indicadores em estado de alerta que demandam ações'. Talvez no plural, talvez ficasse melhor". O print do diagnóstico `cajuina cristal` confirma que hoje aparece **"demandam ação"** (singular). A spec §4.7.1 já tinha "ações" — implementação divergiu da spec.

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- Spec V2 §4.7.1 (texto canônico dos vereditos).
- IDR-010 (motor_version + golden hashes).

## O quê

Trocar "ação" por "ações" no texto fixo do veredito `em_alerta` na config do motor. Decidir junto com o Arquiteto se isso força bump `motor_version 1.2.0 → 1.2.1`.

## Por quê

Alinha implementação com spec §4.7.1 e absorve devolutiva sem custo.

## Critérios de aceite

- [ ] **CA-1 (texto correto):** O veredito `em_alerta` na config retorna a string `"Sua empresa apresenta indicadores em estado de alerta que demandam ações."` (com plural, com ponto final).
- [ ] **CA-2 (regressão):** Vereditos `saudavel`, `precisa_atencao` e `fallback` ficam intocados; teste de snapshot da config motor confirma.
- [ ] **CA-3 (bump version):** Decidido com Arquiteto: **bump `1.2.0 → 1.2.1`** OU **patch silencioso** (texto é renderizado em runtime, não congelado no snapshot). Default sugerido pelo PO: **patch silencioso** se o texto NÃO entra no `indicadores_calculados` (snapshot da IDR-010). Confirmar com IDR rápida ou aditivo na IDR-010.
- [ ] **CA-4 (goldens):** Se bump for necessário (CA-3), 5 golden hashes re-emitidos. Se patch silencioso, goldens intocados.
- [ ] **CA-5 (testes):** 1 teste assertivo no `ResumoExecutivoTest` (cenário `em_alerta`) verifica a string completa.

## Fora de escopo

- Outros textos de veredito.
- Reescrita do algoritmo §4.7.1.

## Padrões de qualidade exigidos

- Sem regressão em testes existentes (511+ Pest, 695+ asserções).
- Se houver bump de motor_version, IDR-010 aditivo + golden hashes re-emitidos.

## Dependências

- **Bloqueada por:** nada.
- **Bloqueia:** nada.

## Decisões já tomadas

- **Texto canônico:** `"Sua empresa apresenta indicadores em estado de alerta que demandam ações."` (plural, ponto final).
- **Bump version:** decisão a tomar com Arquiteto (CA-3) — default patch silencioso.

## Liberdade técnica do agente

- Se houver bump, registrar IDR ou aditivo na IDR-010.
- Se patch silencioso, documentar no commit + nota no `motor.php` explicando que textos de veredito são render-time e não impactam snapshot.

## Definição de Pronto

- [ ] CA-1..CA-5 passam.
- [ ] Suíte Pest verde (511+ testes).
- [ ] CI verde.
- [ ] Deploy em homol verificado (re-abrir diagnóstico Vermelho e ver "ações").
- [ ] `index.json` atualizado.

## Notas do agente

### Decisões tomadas
- 

### Cobertura final
- 

### Links de evidência
- 
