---
pdr_id: PDR-012
slug: logar-como-no-backoffice
title: Antecipar o "Logar como" no backoffice, com auditoria de toda a sessão
status: accepted
decided_at: 2026-11-04
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-011]
related_adrs: [ADR-003]
related_idrs: [IDR-009]
---

# PDR-012 — "Logar como" no backoffice

## Contexto

O PDR-011 §3 deixou impersonar para a v1.2. O PO pediu a antecipação: a equipe precisa ver e operar a conta de um usuário como ele a vê.

## Decisão

1. Altera o PDR-011 §3 só neste ponto: a equipe da allowlist pode assumir o acesso de um usuário a partir de `/backoffice/usuarios`. O resto do backoffice continua só-leitura.
2. Pode agir como o usuário, sem restrição de escrita, e volta ao admin quando quiser.
3. Não pode ser assumido: outro membro da equipe, conta excluída ou anonimizada.
4. Toda requisição da sessão vai para `audit_logs`, com o admin como ator e o usuário como alvo, incluindo os campos enviados. Só credenciais são mascaradas.

## Consequências

- Os eventos de auditoria do domínio gravados na sessão trazem o admin em `actor_id`.
- `evento_produto` não diferencia a sessão assumida: ações do admin contam nas métricas de produto como do usuário.
- O usuário não é avisado de que sua conta foi acessada, e os documentos legais ainda não citam esse acesso. Revisar com o jurídico (LGPD).
