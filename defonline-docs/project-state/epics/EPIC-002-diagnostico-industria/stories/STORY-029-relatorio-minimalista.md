---
story_id: STORY-029
slug: relatorio-minimalista
title: Relatório web minimalista — 7 indicadores + semáforo + glossário inline
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: ready
owner_agent: claude-programador
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: M
---

# STORY-029 — Relatório web minimalista

> Esta estória entrega o **primeiro relatório visível em homologação** — a saída do motor da STORY-028 renderizada como página web dentro do app shell. Caminho feliz ponta a ponta vivo no Checkpoint 1.

## Contexto

A epic.md descreve o relatório como saída web em **≤ 3s p95** com 14 indicadores + semáforo + Resumo Executivo + glossário. Nesta estória entregamos a versão minimalista: 7 indicadores (V1), NCG absoluto informativo, semáforo, glossário inline. Sem Resumo Executivo (STORY-031), sem recomendações da matriz (STORY-032), sem tooltips no quiz (STORY-033).

## O quê

1. **Rota `/diagnosticos/{diagnostico}`** dentro do app shell autenticado.
2. **View Blade `diagnostico-show.blade.php`** com:
   - Cabeçalho: nome da empresa, data do diagnóstico, badge "MVP" pequena (transparência: relatório em evolução).
   - Lista/tabela dos 7 indicadores com colunas: nome, valor formatado, farol (componente visual), mensagem curta.
   - NCG absoluto exibido em card separado, sem farol, com a mensagem informativa da faixa.
   - Glossário inline: ao final do relatório, ou expansível por indicador, com texto do Anexo I (espec V2 `anexos/anexo-I-glossario.md`).
   - Rodapé: `motor_version`, `matrix_version`, data de geração, aviso legal curto.
3. **Componente `<x-farol>`** (verde / amarelo / vermelho / sem-cor) — visual definido pelo design system (decisão pequena do programador; usar tokens existentes, sem hex literal). Acessibilidade: cores **+ ícone** para daltônicos.
4. **Mobile-first:** em desktop, tabela densa. Em mobile, card por indicador.
5. **Loading state:** ao chegar via redirect do quiz (STORY-027), tela mostra skeleton enquanto motor finaliza (geralmente já está pronto, mas garantir robustez).
6. **Acessibilidade:** AA básico. `<main>` semântico, headings na ordem, `<table>` com `<th scope>`, alt text em ícones.

## Por quê

Sem render, motor é invisível. Este é o entregável demonstrável do Checkpoint 1.

## Critérios de aceite

- [ ] **CA-1 (Rota e auth):** `/diagnosticos/{id}` autenticada, com policy: só `usuario_id` dono da empresa vê (alinha com STORY-021 — 403 vs 404 cross-tenant; aplica veredito da IDR daquela spike).
- [ ] **CA-2 (Layout dentro do shell):** breadcrumb `Minhas Empresas › {nome} › Diagnóstico de {data}`. Header do shell e footer institucional preservados.
- [ ] **CA-3 (7 indicadores renderizados):** cada indicador mostra nome, valor (formatado com casas decimais apropriadas), farol visual e mensagem curta. Para `disponivel = false`, mostra linha cinza com mensagem semântica.
- [ ] **CA-4 (NCG absoluto sem farol):** card separado com valor + mensagem da faixa.
- [ ] **CA-5 (Glossário):** texto do Anexo I disponível inline (expansível ou seção ao final). Sem link externo.
- [ ] **CA-6 (Mobile-first):** viewport 360x800 mostra cards empilhados, sem scroll horizontal. Viewport 1280x800 mostra tabela densa.
- [ ] **CA-7 (p95 ≤ 3s):** medição em homol — 50 navegações `quiz submit → relatório carregado completo (DOMContentLoaded)`. p95 ≤ 3s.
- [ ] **CA-8 (Acessibilidade AA):** contraste ≥ 4.5:1; estados de farol não dependem só de cor (ícone + texto também); navegação por teclado.
- [ ] **CA-9 (Versão visível no rodapé):** `motor v1.0.0 · matriz dez-2025 · gerado em DD/MM/AAAA HH:MM`. Estilo discreto.
- [ ] **CA-10 (Testes):** Pest feature (200 OK, conteúdo presente, cross-tenant 403 ou 404 conforme IDR-007), Dusk smoke (caminho do quiz ao relatório).
- [ ] **CA-11 (Cobertura ≥ 80%).**

## Fora de escopo

- 7 indicadores restantes (STORY-030).
- Resumo Executivo (STORY-031).
- Recomendações textuais da matriz DEZ/2025 (STORY-032).
- PDF do relatório (EPIC-007).
- Compartilhamento por link público (não previsto no MVP).

## Dependências

- **Bloqueada por:** STORY-026, STORY-027, STORY-028, EPIC-004 (componentes), STORY-021 (veredito 403 vs 404).
- **Bloqueia:** STORY-030/031/032 estendem este render.

## Decisões já tomadas

- Render server-side (Blade), sem SPA, sem WebSocket. Simples e suficiente.
- Glossário inline (não modal, não página separada) — reduz fricção.
- Farol = cor + ícone (acessibilidade).
- Mobile = card-per-indicador; desktop = tabela densa.

## DoD

- CA-1 a CA-11 passam.
- Demo gravada no Checkpoint 1 (PO faz screencast).
- Tag `rc.W25S1.3`. `index.json` atualizado.

## Protocolo do agente

Padrão. Avisar PO em `in_review`.

## Notas do agente

*(A preencher.)*
