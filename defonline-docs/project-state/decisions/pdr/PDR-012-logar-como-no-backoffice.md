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
3. Não pode ser assumido: admin que também tem a permissão de "Logar como" (inclui quem está em `BACKOFFICE_EMAILS` e o próprio admin), conta excluída ou anonimizada. Admin sem essa permissão pode ser assumido, e a sessão não dá acesso ao backoffice dele. (Revisto pelo PO em 2026-11-04; antes, nenhum membro da equipe podia ser assumido.)
4. Toda requisição da sessão vai para `audit_logs`, com o admin como ator e o usuário como alvo, incluindo os campos enviados. Só credenciais são mascaradas.
5. Ações e métricas da sessão indicam o admin que as fez: no próprio registro, nos eventos de produto, nas métricas e nas telas.

## Consequências

- Os eventos de auditoria do domínio gravados na sessão trazem o admin em `actor_id`.
- As tabelas de domínio e `request_metrics` ganham `personificado_por_id`; `evento_produto` e a métrica de consulta à Receita ganham a marca `personificacao` com `admin_id` e `sessao_id`. Quem calcula métrica de produto decide se conta ou exclui esses eventos.
- O usuário vê o nome do admin na empresa, no diagnóstico e na capa do PDF. Os documentos legais ainda não citam esse acesso. Revisar com o jurídico (LGPD).
