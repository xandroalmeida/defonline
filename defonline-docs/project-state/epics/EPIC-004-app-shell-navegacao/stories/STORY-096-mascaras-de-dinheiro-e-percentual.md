---
story_id: STORY-096
slug: mascaras-de-dinheiro-e-percentual
title: "Componentes de máscara para dinheiro e percentual em todo o sistema"
epic_id: EPIC-004
sprint_id: null
type: feature
target_role: programador
requires_design: false
status: in_review
owner_agent: claude-opus-5-5
created_at: 2026-11-06
updated_at: 2026-11-06
estimated_session_size: S
---

# STORY-096 — Componentes de máscara para dinheiro e percentual em todo o sistema

Origem: pedido do PO em 2026-11-06, ao ver o campo de percentual do cupom aceitar `0000`. O requisito é o RNF-UI-01 (`especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §11).

## Critérios de aceite

- [x] **CA-1:** `<x-input-moeda>` e `<x-input-percentual>` existem, e a lógica da máscara fica em um só lugar (`resources/js/mascaras.js`).
- [x] **CA-2:** Quiz, DRM, planos, pacotes e cupons usam os componentes, sem máscara própria de dinheiro ou percentual.
- [x] **CA-3:** Percentual não aceita letras nem zeros à esquerda, e o limite continua sendo validado no servidor.
- [x] **CA-4:** Trocar o tipo de desconto do cupom limpa o valor digitado.
- [x] **CA-7:** O percentual do cupom aceita uma casa decimal, de 0,1% a 100%, gravado em décimos em `cupons.valor` (PDR-020, decisão 13).
- [x] **CA-5:** Teste automático reprova campo novo que fuja da regra.
- [x] **CA-6:** O valor gravado é igual ao exibido, com os testes de digitação tecla a tecla do quiz e do DRM passando.

## Fora de escopo

- Máscaras de dias, CPF, CNPJ e telefone, que seguem como estão.
- Campos de quantidade inteira da área administrativa (cota, ordem, validade, limite de usos).
