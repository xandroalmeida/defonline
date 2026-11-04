---
pdr_id: PDR-011
slug: backoffice-minimo-somente-leitura
title: Antecipar um backoffice mínimo, só-leitura, restrito à equipe interna
status: accepted
decided_at: 2026-11-03
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-011]
related_adrs: [ADR-001, ADR-003]
related_idrs: [IDR-009]
---

# PDR-011 — Backoffice mínimo, só-leitura, restrito à equipe interna

## Contexto

O "Back-office EB Parcerias" (`defonline-docs/especificacao/V2/roadmap-pos-v1.md` §6.1) está previsto para a v1.2 e foi deixado fora da próxima onda (`roadmap/next-wave.md`). Hoje a equipe só vê quem se cadastrou consultando o banco direto, o que é lento e expõe dados de produção a quem só precisa de uma lista.

## Decisão

1. Antecipar uma fatia mínima do backoffice: **listas só-leitura**, começando pelos **usuários** (STORY-065); empresas vêm depois, em estória própria.
2. Acesso só para uma **allowlist de e-mails da equipe**, definida em configuração (sem tela de gestão de papéis). Quem não está na lista recebe 404, como no IDR-009.
3. Sem nenhuma ação de escrita (editar, bloquear, excluir, impersonar). Isso continua no escopo da v1.2. *(Impersonar foi antecipado pelo PDR-012.)*
4. Todo acesso ao backoffice gera audit log. Os dados pessoais exibidos são só os necessários (CPF mascarado).

## Consequências

- O §6.1 da v1.2 continua valendo e vai crescer a partir desta base.
- A escolha da ferramenta (Filament ou telas próprias) é do Arquiteto/Programador e vira IDR (ADR-001 já cita o Filament como opção).
