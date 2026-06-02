---
story_id: STORY-040
slug: card-resumo-executivo-clicavel
title: Card de destaque do Resumo Executivo — scroll-to + highlight
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
type: bugfix
target_role: programador
requires_design: true
status: in_review
owner_agent: programador (claude-opus-4-8)
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

### Plano (registrado antes de codar — 2026-06-02)

**Documentos lidos:** estória inteira + briefing; componentes `resumo-executivo`, `linha-indicador`,
`card-indicador`, `card-informativo`, página `diagnosticos/show.blade.php`; `IndicadorFormatter`
(ORDEM_ESSENCIAIS), `ResumoExecutivo` (CODIGOS_ANEXO_D, geração de destaques); `tokens.css`,
`app.css`, `app-header` (sticky `h-16` = 64px, `z-30`); `DiagnosticoFactory`; testes existentes.

**Entendimento consolidado:** os "cards de destaque" do Resumo Executivo são, na implementação real,
itens `<li>` de uma `<ul>` (cada um com `data-codigo`). O texto completo de cada destaque já existe
como linha na tabela de indicadores (desktop `<tr>`) e como card no mobile (`<article>`), ambos com
`data-codigo`. Hoje os destaques não são clicáveis → dead-end. Objetivo: tornar cada destaque um
link que rola suavemente até o indicador correspondente e o destaca por ~1s.

**Descoberta importante (duplicidade de id):** os dois layouts (tabela ≥md e cards <md) renderizam o
MESMO indicador simultaneamente no DOM. Usar `id="indicador-{codigo}"` nos dois geraria id duplicado
(HTML inválido; `:target`/jump iria sempre para o primeiro = a tabela, escondida no mobile).
**Decisão:** id canônico só na `<tr>` desktop (fallback no-JS + `:target`). O caminho real (JS leve,
sem lib — Alpine/Livewire já presente, mas aqui basta vanilla em `app.js`) seleciona o alvo VISÍVEL
no breakpoint atual via `[data-codigo]` dentro de `#indicadores-detalhados` (escopo que exclui os
`<li>` do resumo, que também têm `data-codigo`).

**Dúvidas:** nenhuma — decisões de UX (scroll-to + highlight ~1s, sem expand) já fechadas no briefing.

**Plano (3–5 bullets):**
1. Token `--color-highlight-pulse` em `tokens.css`; CSS de `scroll-behavior: smooth`, `.indicador-alvo`
   (scroll-margin p/ header sticky) e animação de highlight (`:target` + classe `.is-highlight`) em
   `app.css`, com `prefers-reduced-motion`.
2. `resumo-executivo.blade.php`: cada destaque vira `<a href="#indicador-{codigo}" data-indicador-link>`
   com afordância visual (sublinhado pontilhado tertiary + `cursor:pointer` nativo) e sufixo sr-only.
3. `linha-indicador.blade.php`: `id` + classe `.indicador-alvo` na `<tr>`. `card-indicador.blade.php`:
   só classe `.indicador-alvo` (sem id, p/ não duplicar). `show.blade.php`: `id="indicadores-detalhados"`.
4. `app.js`: progressive enhancement — click nos `[data-indicador-link]` rola até o alvo visível +
   highlight de 1s; sem JS, o `<a href>` ainda navega (CA-6).
5. Testes: Pest (markup: links presentes c/ destaque, ausentes sem destaque [CA-7], id/classe na tabela)
   + 2 Dusk (desktop: scrollY↑ + alvo visível + `.is-highlight`; mobile: scrollY↑ + card visível).

### Decisões tomadas
- **Suavidade via JS, não CSS global.** A primeira tentativa usava `html { scroll-behavior: smooth }`
  global — isso quebrou 9 testes Dusk NÃO relacionados com `ElementClickInterceptedException` (o
  WebDriver rola o elemento p/ a viewport e clica antes da animação assentar; o alvo "foge"). Removido.
  A suavidade vem do JS (`scrollIntoView({behavior:'smooth'})`); sem JS o fallback é um jump instantâneo
  — explicitamente aceito pelo CA-6 ("jump, sem suavidade").
- **id canônico só na `<tr>` desktop** (`card-indicador` mobile fica só com a classe `.indicador-alvo`,
  sem id) para não duplicar id no DOM. O JS resolve o alvo VISÍVEL por `data-codigo` dentro de
  `#indicadores-detalhados`.
- **Componente `<x-relatorio.destaque-link>`** extraído p/ não duplicar o markup do link entre destaque
  negativo e positivo (regra de dois usos + legibilidade do componente pai).
- **Token novo `--color-highlight-pulse`** (`rgba(99,91,255,0.16)` — Tertiary suave) em `tokens.css`;
  registrado no design-system canônico (mudança aditiva, sem bump de versão). Mantém single-accent.
- **Afordância (CA-4):** sublinhado pontilhado em Tertiary + `cursor:pointer` nativo do `<a>`; vira
  sólido no hover/foco. Texto não passou a truncar (não truncava antes; "não muda" satisfeito).
- **A11y (CA-5):** cada destaque é um `<a href="#indicador-…">` nativo (role=link, focável por teclado,
  Enter aciona) + sufixo `sr-only` explicando o destino. `prefers-reduced-motion` → realce estático.

### Descobertas
- Os "cards de destaque" são, na prática, itens `<li>` de uma `<ul>` (não cards). Os `<li>` também
  carregam `data-codigo` — por isso o JS escopa a busca em `#indicadores-detalhados` (exclui o resumo).
- Os dois layouts (tabela ≥md / cards <md) coexistem no DOM — origem da decisão de id único na `<tr>`.
- Códigos de destaque (CODIGOS_ANEXO_D) são subconjunto dos da tabela exceto `ncg_absoluto` (informativo,
  farol=nenhum) — que nunca vira destaque; logo todo alvo de destaque existe na tabela.
- `getClientRects().length` é o teste de visibilidade confiável p/ `<tr>` (offsetParent é null em
  linhas de tabela mesmo visíveis).

### Cobertura final
- **Pest:** 826 passed (2508 assertions). Cobertura geral **97,3%** (gate ≥80) · Domínio **99,6%** (gate ≥98).
- **Dusk:** suíte completa 22 passed (incl. 2 novos: desktop scrollY↑ + `.is-highlight`; mobile scrollY↑ + card).
- **Pint** limpo · **PHPStan** (nível 6) sem erros.
- Novos testes: `DiagnosticoShowTest` (CA-1/CA-2/CA-5 links; CA-7 regressão sem destaque) +
  `CardResumoExecutivoBrowserTest` (CA-1/CA-2/CA-3/CA-8).

### Links de evidência
- Screenshots locais (gitignored, anexar no PR): `app/tests/Browser/screenshots/story-040-1-resumo-clicavel.png`,
  `…-2-linha-destacada.png`, `…-3-mobile-card-destacado.png` — confirmam clicabilidade, scroll com offset
  do header sticky e highlight na linha (desktop) e no card (mobile).
- Pendente: PR + CI verde + deploy/screenshot em homologação + Lighthouse a11y ≥95 (rodar na URL de homol).
