# Acessibilidade — piso obrigatório

Acessibilidade no DEFOnline **não é modo separado**. É a única forma de desenhar. Este documento descreve o **piso obrigatório** — o que toda tela atende, sem exceção — e dá heurísticas práticas para o Designer aplicar e verificar.

Para o produto, vale o **WCAG 2.1 nível AA** como referência. Você não precisa decorar a especificação inteira — precisa internalizar os pontos abaixo.

## Por que importa para o DEFOnline

A persona inclui contadores e gestores de qualquer idade — incluindo profissionais com baixa visão, dificuldade motora, fadiga ocular ao fim do dia. Acessibilidade não é "para deficientes" — é para todos os contextos reais de uso (incluindo seu, num celular ao sol, com uma mão segurando o café).

Acessibilidade insuficiente não é trade-off — é bug.

## Os 7 pontos do piso

### 1. Contraste

| Tipo de texto | Contraste mínimo |
|---|---|
| Texto normal (<18pt regular ou <14pt bold) | **4.5:1** |
| Texto grande (≥18pt regular ou ≥14pt bold) | **3:1** |
| Ícone significativo, borda de componente interativo | **3:1** |
| Texto decorativo, logo, desabilitado | (isento, mas evite ofender) |

**Como verificar:** ferramenta de contraste do browser, plugin (axe DevTools, Stark) ou WebAIM Contrast Checker. Toda combinação **cor de texto / cor de fundo** usada em token passa por verificação.

**Sinais de erro:**
- Texto cinza claro (`#C0C0C0`) sobre branco — falha.
- Placeholder cinza claro como fonte primária de informação — falha (placeholder ≠ label).
- Cor primária sobre cor de marca saturada — verificar caso a caso.

### 2. Foco sempre visível

- Todo elemento interativo (botão, link, input, checkbox, item de menu) tem **anel de foco visível** quando recebe foco.
- Token `color.border.focus` é o anel padrão (2px sólido).
- **`outline: none` sem substituto é bug.** Se remover o outline padrão, substitua por algo claramente visível.
- Foco precisa ser visível em **todos os contextos** — fundo claro, fundo escuro, fundo colorido.

**Sinal de erro:** "removi o outline porque ficou feio." → falhou.

### 3. Navegação por teclado completa

- **Tab** percorre todos os interativos na ordem visual (top→bottom, left→right em LTR).
- **Shift+Tab** percorre para trás.
- **Enter / Space** ativam botões.
- **Setas** navegam dentro de listas, menus, abas, radio groups.
- **Esc** fecha modal, popover, drawer.
- **Modal tem foco-trap**: tab dentro do modal cicla; foco volta para o gatilho ao fechar.
- **Skip link** ("Pular para conteúdo principal") em telas com navegação extensa.

**Como verificar:** desplugue o mouse. Consegue fazer a tarefa? Se não, falhou.

### 4. Semântica HTML correta (recomendação ao Programador)

Você desenha — Programador implementa. Mas você sugere no spec:

- Botão de ação = `<button>` (não `<div>` com `onClick`).
- Link de navegação = `<a href>` (não `<button>`).
- Label de campo = `<label for>` associado ao input.
- Cabeçalho de seção = `<h1>`–`<h6>` com hierarquia coerente.
- Lista = `<ul>` / `<ol>` (não `<div>` em sequência).
- Tabela tabular = `<table>` com `<th scope>` (não layout em tabela).

Semântica correta = leitor de tela funciona, busca do browser funciona, comportamento padrão (atalhos, foco) funciona.

### 5. Erros não são só cor

- Borda vermelha sozinha **não basta** — daltonismo é comum.
- Mensagem de erro **textual** associada ao campo via `aria-describedby` (ou equivalente).
- Ícone de erro **com label acessível** (não só visual).
- Resumo de erros no topo do form (para form longo) com link para cada campo com erro.

**Sinal de erro:** "campo fica vermelho quando dá erro" — sem texto associado, daltônico não sabe.

### 6. Ícone sozinho como ação tem label

- Botão com **só ícone** (ex.: lupa, lixeira, três pontos) tem `aria-label` descritivo.
- Em mobile, considerar **label visível** (texto curto abaixo do ícone) para clareza.
- Tooltip **não substitui** label acessível (tooltip não roda em mobile, não roda em teclado).

**Convenção sugerida ao Programador:** `aria-label` com verbo + objeto: `aria-label="Excluir empresa"`.

### 7. Alvo de toque adequado

- Mínimo **44×44px** em mobile (WCAG AA recomendação).
- 48×48px preferível.
- Alvos próximos ≥ 8px de distância para evitar toque errado.
- Botões de ação primária maiores que secundários.

**Sinal de erro:** ícone de 24px sem padding ao redor → área de toque insuficiente.

## Heurísticas extras (boas práticas além do piso)

- **Live regions** (`aria-live="polite"` ou `assertive`) para mensagens dinâmicas — toast de sucesso, erro assíncrono, contagem regressiva.
- **Modal aria** correto: `role="dialog"`, `aria-modal="true"`, `aria-labelledby` apontando para o título.
- **Form com `<fieldset>` + `<legend>`** quando agrupa campos relacionados.
- **Imagem decorativa com `alt=""`** explícito (vazio mesmo) para leitor de tela pular.
- **Imagem com conteúdo com `alt`** descritivo.
- **Vídeo com legendas** (CC) e transcrição.
- **Animação respeitando `prefers-reduced-motion`** — usuário que pediu menos movimento, recebe menos.

## Como o Designer verifica (antes do merge)

Checklist rápido na revisão do que o Programador implementou:

- [ ] Contraste de cada combinação cor texto/fundo verificado em browser/ferramenta.
- [ ] Tab percorre na ordem visual; foco visível em todo elemento.
- [ ] Desplugar mouse: consegue completar a tarefa principal só com teclado?
- [ ] Botões com só ícone têm `aria-label`?
- [ ] Erros de form têm texto associado ao campo?
- [ ] Alvos de toque ≥ 44px em mobile?
- [ ] Modal fecha com Esc; foco volta para o gatilho?
- [ ] Live regions onde aplicável (toast, erro assíncrono)?
- [ ] Esquema de cor não é o único canal de informação?

Se algum ❌, é bloqueio do PR.

## Ferramentas úteis

- **axe DevTools** (extensão de browser) — varre violações automáticas. Boa para piso, não para tudo.
- **Lighthouse** (no Chrome) — auditoria de acessibilidade junto com performance.
- **WebAIM Contrast Checker** — verificação manual de contraste.
- **Leitor de tela** (VoiceOver no Mac/iOS, TalkBack no Android, NVDA no Windows) — teste real ocasional vale ouro.
- **Teclado sozinho** — o teste mais simples e mais revelador.

## O que NÃO é desculpa

- "É só primeira versão" — acessibilidade básica é cara de adicionar depois.
- "Ninguém vai usar com leitor de tela" — você não sabe.
- "Designer aprovou visualmente" — visualmente ≠ acessivelmente.
- "Componente da lib não suporta" — Programador pode escalar; lib que impede acessibilidade básica é decisão errada de ADR (escala para Arquiteto).
- "Vai retardar a entrega" — bug de acessibilidade é bug. Não entra em produção.

## Quando dúvida, o piso vence

Acessibilidade entra no Princípio #5 (`design-principles.md`). O **piso WCAG 2.1 AA é intransponível** — vence qualquer outro princípio em conflito. Conflito aparente com simplicidade ou tom geralmente se resolve com mais cuidado de design, não com remoção de acessibilidade. Refinamentos acima do piso (boas práticas extras) entram na hierarquia normal de conflito.
