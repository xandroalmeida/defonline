---
story_id: STORY-107
slug: clientes-do-cupom
title: "Relatório dos clientes que vieram por cada cupom (acompanhamento de parceria)"
epic_id: EPIC-005
sprint_id: null
type: feature
target_role: programador
requires_design: false
status: in_review
owner_agent: claude-opus-5-5
created_at: 2026-11-18
updated_at: 2026-11-18
estimated_session_size: S
---

# STORY-107 — Clientes do cupom

Origem: item 6 da devolutiva do PO (2026-11-18). Uma empresa parceira vai validar a ferramenta com 30 clientes usando um cupom de 100%. A equipe precisa ver quem veio por esse cupom e o que fez, e mandar isso ao parceiro.

## Critérios de aceite

- [x] **CA-1:** Cada cupom em Cupons tem o botão "Clientes", que abre o relatório (permissão `cupons.gerenciar`; 404 para os demais).
- [x] **CA-2:** O relatório lista quem usou o cupom no checkout e quem o recebeu por concessão, sem repetir pessoa. Para cada um: nome, e-mail, WhatsApp, situação (usou / recebeu e não usou), data, desconto recebido, plano atual, empresas, diagnósticos e data do último diagnóstico. Com a permissão "Ver usuários", o nome leva à ficha.
- [x] **CA-3:** No topo, os totais: clientes, quantos usaram, quantos estão com plano ativo, quantos fizeram diagnóstico e o total de diagnósticos.
- [x] **CA-4:** "Baixar planilha" gera um CSV para Excel (`;`, UTF-8 com BOM) com as mesmas colunas, para enviar ao parceiro.
- [x] **CA-5:** Abrir o relatório e exportar a planilha são auditados. Usuário anonimizado aparece sem dados pessoais.

## Testes

- `tests/Feature/Backoffice/ClientesDoCupomTest.php`
