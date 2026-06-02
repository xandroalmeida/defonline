---
story_id: STORY-040
slug: card-resumo-executivo-clicavel
title: Card de destaque do Resumo Executivo — scroll-to + highlight
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
type: bugfix
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-06-02
updated_at: 2026-06-02
estimated_session_size: S
related_devolutiva: "Áudio Edvan 2026-05-27 — 'pega o primeiro, NCG sobrevivendo, aí ele começa, tem duas linhas e o texto lá embaixo tem quatro, ele não complementa. E clicando nele não abre.'"
---

# STORY-040 — Card de destaque do Resumo Executivo: clicável + highlight da linha

> Cards de destaque (até 3 indicadores) no Resumo Executivo da STORY-031 truncam o texto e **não são clicáveis** — o texto completo está mais abaixo, na tabela detalhada. Esta estória torna os cards clicáveis: clique faz scroll suave até a linha correspondente da tabela e aplica highlight temporário (~1s).

## Contexto

Devolutiva do Edvan (áudio 2026-05-27): "tem duas linhas e o texto lá embaixo tem quatro, ele não complementa. E clicando nele não abre." No print da `cajuina cristal` (Diagnóstico de 27/05/2026, verão MVP, farol Vermelho), o card "NCG / Vendas: Não é recomendável a elevação das atividades e da NCG, se não acompanhada do..." termina com elipse. O texto completo aparece como linha na tabela detalhada, gerando **redundância visual + dead-end** (usuário tenta clicar, não acontece nada).

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- STORY-029 entregou a tabela detalhada (`<x-relatorio.tabela-indicadores>` e variante `<x-relatorio.cards-indicadores>` mobile).
- STORY-031 entregou o componente `<x-relatorio.resumo-executivo>`.
- Design system: tokens v1 — usar `--color-accent` para highlight ou definir token novo se ausente.

## O quê

Tornar cada card de destaque do Resumo Executivo (componente `<x-relatorio.resumo-executivo>`) clicável, com comportamento: ao clicar, faz **scroll suave** (`scroll-behavior: smooth` + offset do header sticky se houver) até a linha/card do indicador correspondente na tabela detalhada e aplica **highlight de ~1s** (mudança de background com transição CSS).

## Por quê

Resolve dois problemas simultaneamente: (a) usuário tenta clicar e não acontece nada (UX quebrada), (b) texto truncado no card gera frustração — agora o card é uma "porta de entrada" para a linha completa. Mantém o resumo executivo enxuto e elimina sensação de redundância.

## Critérios de aceite

- [ ] **CA-1 (clique navega):** Dado um Resumo Executivo com ≥1 card de destaque, quando o usuário clica em um card, então a página rola suavemente até a linha do mesmo indicador na tabela detalhada e ela fica destacada por ~1s.
- [ ] **CA-2 (offset correto):** O scroll para no topo da linha alvo + offset suficiente para que ela fique visível abaixo do header (não escondida atrás dele em mobile).
- [ ] **CA-3 (mobile - cards):** A tabela detalhada é renderizada como cards em ≤ 768px (STORY-029); o scroll/highlight funciona igual — alvo é o card correspondente.
- [ ] **CA-4 (3+ casas decimais no texto):** O texto truncado no card continua truncando com elipse CSS (não muda); cursor `pointer`; visual indica clicabilidade (sublinhado sutil ou cor do título distinta — Designer decide entre opções v1).
- [ ] **CA-5 (a11y):** Card tem `role="link"` ou é um `<a href="#indicador-X">`; foco visível por keyboard; navegação por keyboard funciona (Enter ou Espaço aciona o scroll).
- [ ] **CA-6 (sem-JS-gracioso):** Sem JS, o clique no `<a href="#indicador-X">` ainda navega (jump, sem suavidade). Suavidade vem de CSS `scroll-behavior: smooth` + `:target` highlight — Programador pode também usar JS leve, mas SEM dependência externa.
- [ ] **CA-7 (regressão):** Card sem destaque (apenas card de status, sem indicador específico) não vira clicável.
- [ ] **CA-8 (cobertura):** 2 Dusk: (a) clicar no primeiro card e asserir que `window.scrollY` aumentou e a linha alvo está visível; (b) verificar `:target` ou classe `.highlight` na linha após clique.

## Fora de escopo

- Reescrita do algoritmo de seleção de destaques (§4.7.1) — aguarda Edvan + STORY-036.
- Adicionar expand inline (decisão de UX: **scroll-to**, não expand).
- Modificar o texto do resumo executivo em si.
- Highlight permanente (apenas ~1s e dissolve).

## Padrões de qualidade exigidos

- **A11y:** keyboard + screen reader (label do link cobre indicador).
- **Sem JS pesado:** preferir CSS-only + `:target`.
- **Mobile-first:** testar 375×667 e 390×844.

## Dependências

- **Bloqueada por:** nada.
- **Bloqueia:** nada.

## Decisões já tomadas (não as reabra)

- **Comportamento:** scroll-to + highlight (não expand inline).
- **Duração do highlight:** ~1s (ajustável pelo Programador entre 0,8 e 1,2s).
- **Tokens:** usar tokens v1 do design system; introduzir token de highlight se necessário (`--color-highlight-pulse` ou similar) — registrar no design system canônico se for token novo.

## Liberdade técnica do agente

Você decide:
- CSS-only (`:target` + animation) vs JS mínimo (`scrollIntoView({behavior:'smooth'})` + classe temporária).
- Token de highlight ou usar `--color-accent-soft` existente.
- Marcação semântica do card (`<a>` vs `<button>` com JS).

## Definição de Pronto

- [ ] CA-1..CA-8 passam.
- [ ] 2 Dusk verdes.
- [ ] A11y auditado (Lighthouse a11y ≥ 95 na página do diagnóstico).
- [ ] CI verde.
- [ ] Deploy em homol verificado (screenshot/video curto no PR).
- [ ] `index.json` atualizado.
- [ ] "Notas do agente" preenchidas.

## Notas do agente

### Decisões tomadas
- 

### Descobertas
- 

### Cobertura final
- 

### Links de evidência
- 
