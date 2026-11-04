---
pdr_id: PDR-013
slug: permissoes-de-admin-e-glossario-no-backoffice
title: Permissões por admin no backoffice e glossário mantido pela equipe
status: accepted
decided_at: 2026-11-04
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-011]
related_adrs: [ADR-003]
related_idrs: [IDR-009]
---

# PDR-013 — Permissões de admin e manutenção do glossário

## Contexto

O PDR-011 §2 dava o mesmo acesso a toda a allowlist, sem tela de gestão. O PO pediu para escolher as permissões de cada admin e para a equipe manter o glossário sem depender de deploy.

## Decisão

1. Cada admin tem uma lista de permissões: ver usuários, logar como usuário, gerenciar admins e manter glossário. Não há papéis nomeados nem biblioteca nova: a lista fica em `usuarios.permissoes_admin`.
2. Quem está em `BACKOFFICE_EMAILS` tem todas as permissões e não muda pela tela. Garante que sempre haja quem administre.
3. A tela de Administradores dá acesso a contas já cadastradas e com e-mail confirmado. Não cria conta.
4. Ninguém altera as próprias permissões. Quem gerencia admins pode dar qualquer permissão a outros, e toda alteração gera audit log.
5. Cada item do menu Administração e cada rota exigem a permissão correspondente. Sem ela, 404 (IDR-009).
6. O glossário sai do código para a tabela `glossario_termos`, mantida na tela de Glossário. O texto vale para todos os relatórios na hora, inclusive os já emitidos.

## Consequências

- O Anexo I deixa de ser a origem do texto em produção. Quem edita na tela é responsável por manter a validação da EB Parcerias.
- Altera o PDR-011 §2 (allowlist como único acesso e ausência de tela de gestão) e o §3 (mais duas áreas com escrita).
