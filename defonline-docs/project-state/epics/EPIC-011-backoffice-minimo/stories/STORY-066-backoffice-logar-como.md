---
story_id: STORY-066
slug: backoffice-logar-como
title: Backoffice — "Logar como" um usuário, com volta ao admin e auditoria da sessão
epic_id: EPIC-011
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: in_review
owner_agent: programador (claude-opus-5-5)
created_at: 2026-11-04
updated_at: 2026-11-04
estimated_session_size: M
---

# STORY-066 — Backoffice: "Logar como"

Decisão: PDR-012. A tela `/backoffice/usuarios` também deixa de ficar limitada a 960px e passa a mostrar todas as colunas sem corte a partir de 1280px.

## Critérios de aceite

- **CA-1 quem pode:** só a equipe da allowlist, por POST; comum e não encontrado recebem 404. Outro membro da equipe, conta excluída ou anonimizada não podem ser assumidos e não têm o botão.
- **CA-2 acesso:** a sessão passa a ser a do usuário: vê só os dados dele e não acessa o backoffice. Não conta como login dele em "Último login".
- **CA-3 volta:** aviso fixo em toda tela do app, com o nome do usuário e "Voltar ao admin", que devolve à lista.
- **CA-4 sessão:** "Sair" encerra tudo. Admin fora da allowlist derruba a sessão na próxima requisição. Chave de personificação velha nunca devolve o acesso de admin.
- **CA-5 auditoria:** `audit_logs` guarda `personificacao.iniciada`, `personificacao.encerrada` (com motivo) e uma `personificacao.requisicao` por requisição (método, caminho, rota, query, status, campos e chamadas Livewire), todas com `context.personificacao.sessao_id`. Credenciais são mascaradas. Eventos de domínio da sessão ficam com o admin em `actor_id`.

## Consultar uma sessão

```sql
SELECT occurred_at, action, context->>'metodo', context->>'caminho', context->>'status', context->'entrada'
FROM audit_logs
WHERE context->'personificacao'->>'sessao_id' = '<sessao_id>'
ORDER BY occurred_at;
```

## Notas do agente

- 2026-11-04: implementada. Testes em `tests/Feature/Http/PersonificacaoTest.php` e `tests/Browser/PersonificacaoBrowserTest.php`. Falta publicar e verificar em produção.
