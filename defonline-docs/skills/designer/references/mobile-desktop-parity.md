# Paridade mobile/desktop — mobile-first com propósito

Este documento detalha o **Princípio #2** (`design-principles.md`). "Mobile-first" é fácil de dizer e difícil de fazer — quando feito errado, vira "desktop empilhado" no mobile ou "mobile esticado" no desktop. Nenhum dos dois é paridade.

## A regra básica

> A tela **nasce no mobile** (≥360px). Desktop **herda a estrutura** e usa o espaço extra com **propósito declarado** — nunca é "mobile esticado".

A inversão (desenhar desktop primeiro e "adaptar" para mobile depois) gera dois sintomas previsíveis:

- Mobile fica com 80% do conteúdo escondido ou rolando excessivamente.
- Desktop fica com 80% do conteúdo cabendo apenas porque tem mais espaço — mas com hierarquia confusa, porque foi pensado para superfície que não existe no mobile.

## Os 3 viewports do DEFOnline

| Viewport | Min-width | Quando aplicar |
|---|---|---|
| Mobile | 360px | Sempre — é o ponto de partida |
| Tablet | 768px | **Só** quando comportamento muda relevantemente em relação a mobile/desktop |
| Desktop | 1024px | Sempre — é o ponto de destino |
| Desktop largo | 1440px | Raramente — quando faz sentido usar espaço extra (ex.: lista + detalhe lado a lado mais painel) |

**Tablet só ganha layout próprio quando justificado.** Caso contrário, escala do mobile (até 1023px) ou do desktop (a partir de 768px se a transição cabe). Spec sem tablet é o padrão; tablet aparece como exceção.

## Estratégias de transição mobile → desktop

Quando a tela cresce de mobile para desktop, escolha uma estratégia consciente:

### 1. Empilhar → lado a lado

Conteúdo empilhado verticalmente no mobile vira **colunas** no desktop.

- Form: campos empilhados no mobile; pode virar 2 colunas no desktop quando o agrupamento faz sentido.
- Lista + detalhe: lista ocupa a tela inteira no mobile (com navegação para detalhe); no desktop pode ser **lista à esquerda + detalhe à direita** (master-detail).

### 2. Drawer → persistente

Navegação/filtros em **drawer (off-canvas)** no mobile vira **lateral persistente** no desktop.

- Menu lateral colapsado no mobile, sempre visível no desktop.
- Filtros em drawer no mobile, em sidebar no desktop.

### 3. Bottom nav → top/side nav

Navegação principal **na parte inferior** no mobile (alcance do polegar) vira navegação **lateral** no desktop.

### 4. Modal → inline

Detalhe que abre em **modal full-screen** no mobile pode virar **inline ou drawer lateral** no desktop.

### 5. Tabela → cards

Tabela com mais de **5–7 colunas** no desktop vira **lista de cards** no mobile (cada card mostra os campos importantes; toque abre detalhe).

**Não** faça scroll horizontal infinito de tabela no mobile — é a pior das soluções.

## Anti-padrões comuns

### Mobile esticado

Layout pensado para desktop, simplesmente "esticado" no celular.

**Sintomas:** componentes muito largos, espaço vazio enorme nas laterais (em telas estreitas), CTA em local inalcançável pelo polegar.

**Correção:** redesenhar do zero pelo mobile.

### Desktop empilhado

Layout pensado para mobile, "empilhado" no desktop sem aproveitar o espaço.

**Sintomas:** 80% de espaço vazio nas laterais, scroll vertical longo em desktop, conteúdo "estreitinho" no meio da tela.

**Correção:** declarar **propósito** do espaço extra do desktop (contexto lateral, múltiplas colunas, painel persistente). Sem propósito, declare explicitamente que o conteúdo fica centrado com largura máxima (e diga **por que** — ex.: legibilidade de texto longo).

### Hover-only

Funcionalidade que só aparece com **hover** do mouse — desaparece em mobile (não há hover real).

**Correção:** funcionalidade importante tem trigger acessível em mobile (toque, botão visível, swipe).

### Tabela infinita

Tabela larga rolando horizontalmente no mobile.

**Correção:** tabela vira lista de cards (com os campos importantes visíveis); o resto vai para detalhe.

### Tablet esquecido

Spec sem tablet → no breakpoint 768px–1023px o layout quebra (componente desktop não cabe; mobile estica demais).

**Correção:** ou tablet herda mobile até 1023px (e desktop começa em 1024px), ou tablet herda desktop a partir de 768px (e funciona) — declare qual. Se nenhum dos dois funciona, faça layout específico de tablet.

## Heurísticas de mobile

- **Polegar manda.** Ação primária e navegação acessíveis com o polegar de uma mão segurando o celular — área inferior da tela. CTA primário **na base** ou em **botão flutuante** (FAB) faz sentido.
- **Alvos ≥ 44×44px**, idealmente 48.
- **Sem hover-only.** Tudo navegável por toque.
- **Scroll vertical natural.** Scroll horizontal só intencional e óbvio (carrossel curto, abas).
- **Modal full-screen** em vez de modal pequeno — modal pequeno em mobile é UX ruim.
- **Densidade reduzida em relação ao desktop** — texto e elementos maiores para toque, mais respiração entre eles.
- **Skeleton e não spinner** — performance percebida importa mais no mobile (rede pior).

## Heurísticas de desktop

- **Espaço extra trabalha.** Lateral com contexto, painel persistente, múltiplas colunas quando útil.
- **Hover é informação extra**, não substituto. Tooltip, preview de link, micro-feedback.
- **Atalhos de teclado** em ações frequentes (Cmd+S, Cmd+K para busca, Esc para fechar).
- **Densidade maior**, mas sem aglomerar — espaçamento generoso ainda.
- **Largura máxima** em texto longo (legibilidade — 60–80 caracteres por linha).

## Como o spec documenta isso

No `templates/screen-spec.md`, seção **Layout**, você entrega:

- **Mobile (≥360px)** sempre.
- **Desktop (≥1024px)** sempre.
- **Tablet (768px)** só se comportamento muda relevantemente.

Para o desktop, **declare o que muda em relação ao mobile** — não é "o mesmo, mais largo". Exemplos:

- "Mobile: lista vertical, item abre nova tela. Desktop: lista à esquerda (300px), detalhe à direita (resto da largura)."
- "Mobile: navegação em bottom bar com 4 ícones. Desktop: navegação lateral persistente (240px) com label + ícone."
- "Mobile: filtros em drawer disparado por botão. Desktop: filtros em sidebar lateral colapsável (320px)."

Sem declaração explícita, vira "desktop empilhado".

## Quando desktop e mobile divergem demais

Se a tela mobile e a tela desktop são **profundamente diferentes** (fluxos diferentes, interações diferentes), pergunte:

- É a mesma tarefa? Talvez sejam dois fluxos diferentes em vez de paridade.
- Pode ser indicador de complexidade demais — Princípio #1 (simplicidade) pode estar pedindo simplificação.

Divergência grande pode ser legítima (ex.: edição em massa só faz sentido em desktop) — mas declare em DDR, não em silêncio no spec.

## Checklist mobile-first do spec

- [ ] Sketch mobile (≥360px) presente.
- [ ] Sketch desktop (≥1024px) presente.
- [ ] Tablet considerado (próprio ou herda mobile/desktop declaradamente).
- [ ] CTA primário acessível ao polegar no mobile.
- [ ] Alvos de toque ≥ 44px.
- [ ] Sem hover-only para funcionalidade importante.
- [ ] Espaço extra do desktop tem **propósito declarado**.
- [ ] Tabela com >5–7 colunas tem **alternativa** para mobile (cards).
- [ ] Componentes do DS usados são responsivos por padrão (ou variante declarada por viewport).
