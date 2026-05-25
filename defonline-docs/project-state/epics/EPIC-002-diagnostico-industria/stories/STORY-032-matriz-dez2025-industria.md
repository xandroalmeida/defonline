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
related_idrs: ["IDR-010"]
---

# STORY-032 — Matriz DEZ/2025 (Indústria)

> Acopla as **recomendações textuais por indicador × farol** ao relatório, alimentadas pelo Anexo F da spec, filtradas para a coluna Indústria. Texto é **gravado no snapshot** do diagnóstico (não lido on-the-fly) conforme IDR-010 §Sub-decisão 2.

## Contexto

Anexo F da spec V2 (`defonline-docs/especificacao/V2/anexos/anexo-F-matriz-recomendacoes-dez2025.md`, **autoria EB Parcerias / EBC**) tem texto por **indicador × cor de farol × setor**. Esta estória traz esse texto para dentro do relatório.

A IDR-010 (aprovada 2026-05-25) **já fixou** as decisões arquiteturais relevantes:

- **Versionamento:** `matrix_version = "dez-2025"` (formato datado curto, varchar(16)) — definido em IDR-010 §Sub-decisão 1.
- **Snapshot dos resultados:** o texto da matriz é gravado em `diagnosticos.indicadores_calculados[*].mensagem_detalhada` (JSONB imutável) **no momento do cálculo**, garantindo reprodutibilidade absoluta — IDR-010 §Sub-decisão 2.
- **Matriz como dado versionado, não como código:** arquivo PHP versionado em git carregado por `matrix_version` — IDR-010 §Operacional ("Mensagens da matriz são lidas como dado versionado (config PHP carregado por `matrix_version`), não como template Twig/Blade").

> **Correção 2026-05-25:** a redação original (a) listava decisão "snapshot vs lookup" como aberta no CA-7 — decisão já tomada na IDR-010; (b) usava nome de campo `indicadores_calculados.recomendacao` divergente do schema da IDR-010 (`mensagem_detalhada`); (c) delegava ao programador a escolha "config PHP ou banco" — fixada como **config PHP versionado** na IDR-010. Realinhado.

## O quê

1. **Ingestão da matriz:** seed/loader que lê o Anexo F (markdown) e popula **`config/motor/matriz-dez-2025-industria.php`** — arquivo PHP versionado em git, fonte única lida pelo motor. Sem tabela no banco para a matriz no MVP (IDR-010 §Operacional).
2. **Filtro Indústria:** apenas linhas onde setor = Indústria. Comércio/Serviços ficam fora desta sprint.
3. **Carga por `matrix_version`:** o motor recebe `matrix_version` como parâmetro e carrega o arquivo correspondente (`config/motor/matriz-{matrix_version}-industria.php`). Permite evolução futura sem mexer em código.
4. **Snapshot no momento do cálculo:** o texto recomendado (curto + detalhado) é **interpolado e gravado** em `diagnosticos.indicadores_calculados[*].mensagem_detalhada` no `INSERT` original. Re-renderizar o relatório lê o snapshot — **o motor não é re-invocado** (IDR-010 §Sub-decisão 2).
5. **Componente Blade `<x-recomendacao>`** que recebe `mensagem_curta` + `mensagem_detalhada` (ambos já no snapshot do diagnóstico) e renderiza. **Não lê config** — só renderiza dados do agregado.
6. **Render integrado** no relatório (componente já entregue na STORY-029, `done`): abaixo de cada indicador, expansível por click no mobile, inline no desktop ≥ 1024px.
7. **Fallback gracioso:** se faltar texto para um par (indicador × farol) **no momento do cálculo**, grava placeholder no snapshot (`"Recomendação em revisão"`) + log warning + acresce à lista em `design/matriz-lacunas.md`. **Não quebra cálculo nem relatório.**
8. **Versionamento documentado:** o rodapé do relatório (já entregue) exibe `matrix_version = "dez-2025"`. Bump futuro = novo arquivo `matriz-mar-2026-industria.php` + bump da constante; diagnósticos antigos continuam exibindo texto DEZ/2025 (porque está no snapshot).

## Critérios de aceite

- [ ] **CA-1:** Anexo F lido e carregado em `config/motor/matriz-dez-2025-industria.php` (PHP versionado, sem tabela de banco).
- [ ] **CA-2:** Apenas coluna Indústria carregada (Comércio/Serviços fora). Loader tem teste arquitetural Pest que falha se um setor não-Indústria entrar no array.
- [ ] **CA-3:** Componente `<x-recomendacao>` renderiza texto a partir do snapshot `indicadores_calculados[*].mensagem_curta` + `mensagem_detalhada` — sem consultar config em runtime.
- [ ] **CA-4:** Motor (STORY-028/030, `done`) é estendido para **gravar `mensagem_detalhada` no snapshot** durante `CalcularDiagnostico::execute`. Diagnósticos calculados antes desta estória continuam funcionais (campo `mensagem_detalhada` ausente → componente renderiza só `mensagem_curta`).
- [ ] **CA-5 (fallback):** par sem texto na matriz → `mensagem_detalhada = "Recomendação em revisão."` + log warning + linha adicionada a `design/matriz-lacunas.md` (auto-append pelo loader). PO consolida lacunas com a EBC em backlog dedicado **durante** a execução — não bloqueia.
- [ ] **CA-6:** `matrix_version` continua `"dez-2025"` (carimbado na linha do diagnóstico). Não cria nova versão nesta estória.
- [ ] **CA-7 (testes):** Pest feature confirma render integrado em homol — casos: par com texto, par sem texto (fallback), par com texto longo (overflow inline). Pest unit valida o loader (filtro Indústria, parse de markdown, fallback).
- [ ] **CA-8 (mobile):** mensagem detalhada não quebra layout — expansível por click em mobile (< 1024px), inline em desktop. Tokens do design system v1 (STORY-019 `done`).
- [ ] **CA-9 (snapshot imutável):** teste arquitetural Pest assegura que `indicadores_calculados.mensagem_detalhada` nunca é atualizado após `INSERT` (`update()` em diagnóstico já existente falha).

## Fora de escopo

- Comércio e Serviços — onda 2.
- Edição da matriz pelo PO via UI — roadmap §6 (backoffice).
- Mecanismo de feedback 👍/👎 por recomendação — roadmap §1.4.
- Migração retroativa de diagnósticos antigos para preencher `mensagem_detalhada` — fora do MVP.

## Dependências

- **Bloqueada por:** STORY-031 (resumo + 14 indicadores prontos — **`done`** ✓), IDR-010 (versionamento + snapshot — **`accepted`** ✓).
- **Bloqueia:** STORY-036 (Validador externo revisa as recomendações também).

## Decisões já tomadas

- **Anexo F como fonte da verdade** (autoria EB Parcerias / EBC).
- **Filtro Indústria-only** no MVP.
- **Snapshot da recomendação no momento do cálculo** (IDR-010 §Sub-decisão 2) — não lookup em runtime.
- **Config PHP versionado em git**, sem tabela de banco para a matriz (IDR-010 §Operacional).
- **Schema do snapshot:** `indicadores_calculados[*].mensagem_detalhada` (snake_case PHP-friendly, conforme IDR-010 §Sub-decisão 2).
- Sem UI de edição da matriz nesta sprint.

## DoD

CA-1 a CA-9 + tag `rc.W25S3.1`. `index.json` atualizado.

## Protocolo do agente

Padrão. Reportar lacunas do Anexo F ao PO em backlog dedicado durante a execução (não bloqueia, mas precisa ser visto antes do Validador externo).

## Notas do agente

*(A preencher.)*
