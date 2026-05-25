---
story_id: STORY-032
slug: matriz-dez2025-industria
title: Matriz DEZ/2025 — recomendações por indicador × farol filtradas para Indústria
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: draft
owner_agent: claude-programador
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: M
---

# STORY-032 — Matriz DEZ/2025 (Indústria)

> Acopla as **recomendações textuais por indicador × farol** ao relatório, alimentadas pelo Anexo F da spec, filtradas para coluna Indústria.

## Contexto

Anexo F da spec V2 (`anexos/anexo-F-matriz-recomendacoes-dez2025.md`) tem texto por **indicador × cor de farol × setor**. Esta estória traz esse texto para dentro do relatório. Versionamento via `matrix_version = "dez-2025"`.

## O quê

1. **Ingestão da matriz:** seed/loader que lê o Anexo F e popula `config/motor/matriz-dez-2025-industria.php` (ou banco — decisão do programador; preferência PO: arquivo PHP versionado em git, mais auditável).
2. **Filtro Indústria:** apenas linhas onde setor = Indústria. Comércio/Serviços ficam fora desta sprint.
3. **Componente `<x-recomendacao>`** que recebe `indicador` + `farol` e renderiza o texto correspondente.
4. **Render integrado** no relatório (STORY-029): abaixo de cada indicador, ou expansível por click, mostra a recomendação textual.
5. **Fallback gracioso:** se faltar texto para um par (indicador × farol), mostra placeholder "Recomendação em revisão" + log de warning (não quebra relatório).
6. **Versionamento:** `matrix_version` no `diagnosticos` referencia a versão exata. Mudança da matriz no futuro = bump (`matrix_version = "mar-2026"`); diagnósticos antigos seguem com texto de DEZ/2025 (snapshot dos resultados, mas texto da matriz é lookup — decidir se snapshotar ou não na CA-7).

## Critérios de aceite

- [ ] **CA-1:** Anexo F lido e carregado em config PHP versionado.
- [ ] **CA-2:** Apenas coluna Indústria carregada (Comércio/Serviços fora).
- [ ] **CA-3:** Componente `<x-recomendacao>` renderiza texto por par (indicador × farol).
- [ ] **CA-4:** Relatório (STORY-029) integra as recomendações abaixo de cada indicador.
- [ ] **CA-5 (fallback):** par sem texto → placeholder + log warning. Não quebra. Lista de pares faltantes documentada em `design/matriz-lacunas.md` para PO consolidar com EB.
- [ ] **CA-6:** `matrix_version` continua `"dez-2025"` (não muda nesta estória).
- [ ] **CA-7 (snapshot vs lookup):** decisão tomada e justificada nas Notas do agente. Default PO: snapshot — gravar texto da recomendação dentro de `indicadores_calculados.recomendacao` no momento do cálculo, garantindo reprodutibilidade absoluta mesmo se Anexo F for editado depois.
- [ ] **CA-8 (testes):** Pest feature confirma render integrado. Casos: 1 par com texto, 1 par sem texto (fallback), 1 par com texto longo (truncamento ou expansão).
- [ ] **CA-9 (mobile):** recomendações não quebram layout mobile — expansível por default ou ocupa linha completa.

## Fora de escopo

- Comércio e Serviços — onda 2.
- Edição da matriz pelo PO via UI — roadmap §6 (backoffice).
- Mecanismo de feedback 👍/👎 por recomendação — roadmap §1.4.

## Dependências

- **Bloqueada por:** STORY-031 (resumo + 14 indicadores prontos).
- **Bloqueia:** STORY-036 (Validador externo revisa as recomendações também).

## Decisões já tomadas

- Anexo F como fonte da verdade.
- Filtro Indústria-only.
- Snapshot da recomendação no momento do cálculo (CA-7).
- Sem UI de edição da matriz nesta sprint.

## DoD

CA-1 a CA-9 + tag `rc.W25S3.1`. `index.json` atualizado.

## Protocolo do agente

Padrão. Reportar lacunas do Anexo F ao PO em backlog dedicado durante a execução (não bloqueia, mas precisa ser visto antes do Validador externo).

## Notas do agente

*(A preencher.)*
