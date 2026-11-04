---
story_id: STORY-067
slug: permissoes-admin-e-glossario
title: Backoffice — permissões por admin e manutenção do glossário
epic_id: EPIC-011
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: done
owner_agent: programador (claude-opus-5-5)
created_at: 2026-11-04
updated_at: 2026-11-04
estimated_session_size: M
---

# STORY-067 — Permissões de admin e glossário

Decisão: PDR-013.

## Critérios de aceite

- **CA-1 acesso:** cada tela e cada ação Livewire do backoffice exigem a permissão correspondente; sem ela, 404. `BACKOFFICE_EMAILS` tem todas. Sessão de "Logar como" nunca usa o backoffice.
- **CA-2 menu:** a seção Administração mostra só os itens das permissões do admin, e some se ele não tiver nenhuma.
- **CA-3 cadastro de admin:** a tela de Administradores dá, edita e remove permissões de contas com e-mail confirmado. "Logar como" traz junto "Ver usuários". Ninguém altera a si mesmo nem quem está em `BACKOFFICE_EMAILS`. Toda alteração vai para `audit_logs`, com antes e depois.
- **CA-4 Logar como:** exige a permissão própria. Quem a perde tem a sessão de "Logar como" encerrada. Outro admin não pode ser assumido.
- **CA-5 glossário:** a tela adiciona, edita, exclui e reordena termos, com auditoria. O relatório na tela segue o banco e omite a seção se o glossário estiver vazio. A migration traz os 17 termos atuais.

## Notas do agente

- 2026-11-04: implementada. Falta publicar e verificar em produção.

2026-11-04: fechada por decisão do PO, publicada na v0.11.5 (health, ready e smoke read-only ok). A verificação logada em produção não foi executada pelo agente.
