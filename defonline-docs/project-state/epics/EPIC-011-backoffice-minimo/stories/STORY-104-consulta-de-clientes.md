---
story_id: STORY-104
slug: consulta-de-clientes
title: "Consulta de clientes no backoffice: busca por CPF/CNPJ/WhatsApp e fichas"
epic_id: EPIC-011
sprint_id: null
type: feature
target_role: programador
requires_design: false
status: in_review
owner_agent: claude-opus-5-5
created_at: 2026-11-18
updated_at: 2026-11-18
estimated_session_size: M
---

# STORY-104 — Consulta de clientes

Origem: item 7 da devolutiva do PO (2026-11-18): o admin precisa consultar usuários, empresas e diagnósticos por nome, CPF/CNPJ e WhatsApp. Decisão do PO: o CPF aparece completo na ficha, a lista continua mascarada e o acesso é auditado.

## Critérios de aceite

- [x] **CA-1:** A busca de Usuários aceita nome, e-mail, CPF, telefone e o CNPJ/CPF de uma empresa do usuário, com ou sem máscara. São necessários pelo menos 4 dígitos para buscar por documento.
- [x] **CA-2:** A tela Empresas (menu do backoffice) lista as empresas de todos os usuários, com documento, contato, dono e quantidade de diagnósticos. A busca aceita nome, CNPJ/CPF, telefone ou e-mail.
- [x] **CA-3:** A ficha do usuário mostra CPF completo, e-mail, WhatsApp com link de conversa, plano, análises restantes, créditos válidos, empresas e diagnósticos, e tem o "Logar como". Usuário anonimizado não mostra dados pessoais.
- [x] **CA-4:** A ficha da empresa mostra documento, razão social, cidade, situação na Receita, e-mail, WhatsApp com link, dono com o contato dele e diagnósticos. Com a permissão de captação, o diagnóstico leva à demanda. Empresa excluída continua acessível, marcada.
- [x] **CA-5:** Tudo exige a permissão "Ver usuários" (404 para os demais). Abrir uma ficha é auditado. A auditoria da busca guarda o termo com os dígitos mascarados.

## Testes

- `tests/Feature/Backoffice/ConsultaClientesTest.php`
