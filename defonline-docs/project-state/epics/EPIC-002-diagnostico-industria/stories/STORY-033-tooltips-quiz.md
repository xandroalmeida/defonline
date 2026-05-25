---
story_id: STORY-033
slug: tooltips-quiz
title: Tooltips/box de explicação por indicador no quiz (§6.8)
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: draft
owner_agent: claude-programador
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: S
---

# STORY-033 — Tooltips no quiz

> Adiciona explicação curta por campo no quiz para reduzir erro de entrada. Spec §6.8 + Anexo A §A.6.

## Contexto

A epic.md inclui como entregável: *"Tooltip/box de explicação por indicador no quiz para reduzir erro de entrada (espec §6.8 e Anexo A §A.6)"*. Roberto, sem contador ao lado, pode confundir "PMR" (recebimento) com "PMC" (compras). Tooltip resolve.

> **Status das fontes textuais (2026-05-25):** spec V2 §6.8 ainda marcada `[DECIDIR]` (apenas proposta preliminar: "tooltip acionado por clique em ícone (?) ao lado do label"; conteúdos finais por campo a consolidar). Anexo A §A.6 está reservado mas vazio.
>
> **Decisão do PO em 2026-05-25:** formato fixado em **tooltip inline com click no ícone `?` ao lado do label** (proposta preliminar da spec promovida a oficial). Não inclui priorização por taxa de abandono no MVP (todos os 23 campos ganham tooltip de uma vez).
>
> **Atualização 2026-05-25 (mesmo dia, mais tarde):** os 23 textos foram **extraídos da planilha-fonte e expandidos no formato §6.8** pela equipe de produto. Os 15 financeiros (Q02–Q16) vêm da `DEFweb.net - QUIZ.xlsx` coluna "DESCRIÇÕES EM ÍCONES (?)" (autoria EB Parcerias / EBC); os 8 restantes (Q01 setor + Q17–Q23 captação) foram redigidos pela equipe e ficam como **rascunho a confirmar com EBC** na próxima revisão (não bloqueiam o dev). Textos finais publicados em `defonline-docs/especificacao/V2/anexos/anexo-A-campos-quiz.md §A.6` (tabela 1.0) e em `app/config/quiz/help-industria.php` (config consumido pelo componente). **CA-8 (gate dos textos até 2026-06-05) já está atendido — antecipação de ~11 dias.**

## O quê

1. **Conteúdo:** 23 textos curtos (~50 palavras cada), um por campo do quiz. PO consolida texto até **2026-06-05 (Checkpoint 2)**; programador integra na Semana 3.
2. **Componente `<x-help>`** acoplado ao label do campo, mostra ícone de interrogação. **Click no ícone** abre tooltip — sem hover (igual em desktop e mobile, padrão simples).
    - **Desktop (≥ 1024px):** popover flutuante posicionado abaixo/ao lado do ícone.
    - **Mobile (< 1024px):** bottom-sheet (consistente com app shell v1).
3. **Tokens do Design System v1** (STORY-019 `done`) — cores, tipografia, raio, sombra do `<x-help>` consumidos da camada de tokens (Tailwind v4 theme — IDR-008). Sem cores hard-coded.
4. **Acessibilidade:** `aria-describedby` ligando label ao texto. Tooltip alcançável por teclado (Tab para o ícone, Enter ou Space abre, Esc fecha). Contraste AA mínimo (≥ 4.5:1) já garantido pelos tokens v1.
5. **Textos em config externa** (`config/quiz/help-industria.php`) — facilita ajuste sem refactor.

## Critérios de aceite

- [ ] **CA-1:** Todos os 23 campos do quiz têm tooltip funcional.
- [ ] **CA-2:** Texto pode ser editado em `config/quiz/help-industria.php` sem mudar código de componente nem teste.
- [ ] **CA-3:** Mobile (< 1024px): bottom-sheet ao click no ícone.
- [ ] **CA-4:** Desktop (≥ 1024px): popover flutuante ao click no ícone.
- [ ] **CA-5 (acessibilidade):** `aria-describedby`, Tab/Enter/Space/Esc funcionam, contraste AA — auditado com Pa11y ou similar.
- [ ] **CA-6 (design system):** zero cores/tamanhos hard-coded no componente; tudo via tokens v1 (teste arquitetural Pest assegura).
- [ ] **CA-7 (testes):** Pest feature — view contém os textos esperados (lê de config no setUp). Dusk: click no ícone revela o conteúdo em mobile e desktop.
- [x] **CA-8 (gate do PO):** ~~os 23 textos consolidados pelo PO devem estar publicados em `config/quiz/help-industria.php` (ou em PR equivalente) **até 2026-06-05**. Sem isso, a estória não inicia.~~ **Atendido em 2026-05-25** — `app/config/quiz/help-industria.php` v1.0.0 publicado; Anexo A §A.6 tabela 1.0 publicada. Q01 e Q17–Q23 marcados como `rascunho a confirmar EBC` mas **não bloqueiam o dev** (revisão da EBC pode entrar como PR de polimento durante ou após a STORY-033).

## Fora de escopo

- Vídeos explicativos — roadmap.
- Calculadora inline para campos derivados — roadmap.
- Tooltip também no relatório — fora desta estória (glossário já cumpre).

## Dependências

- **Bloqueada por:** STORY-027 (campos existem — **`done`** ✓), STORY-019 (Design System v1 — **`done`** ✓), ~~PO entregar 23 textos até 2026-06-05~~ **atendido 2026-05-25** ✓.
- **Bloqueia:** nada (parte da V3).

## Pós-condição opcional (não-bloqueante)

- Revisão dos 8 textos marcados `rascunho a confirmar EBC` (Q01, Q17–Q23) na próxima reunião com EB Parcerias. Mudanças entram via PR direto em `app/config/quiz/help-industria.php` + bump da versão no header do arquivo + bump da versão do Anexo A §A.6.

## Decisões já tomadas

- Conteúdo em config externa, não hard-coded.
- Indústria-only.
- Spec V2 §6.8 + Anexo A §A.6 são fontes da verdade (§6.8 promovida de `[DECIDIR]` a oficial em 2026-05-25 — formato tooltip inline com click no ícone `?`).
- Click (não hover) em desktop e mobile — uniformidade.
- Tokens do Design System v1 (IDR-008 Tailwind v4 theme + STORY-019).

## DoD

CA-1 a CA-8 + tag `rc.W25S3.2`. `index.json` atualizado.

## Protocolo do agente

Padrão.

## Notas do agente

*(A preencher.)*
