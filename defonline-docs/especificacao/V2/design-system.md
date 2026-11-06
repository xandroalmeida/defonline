# DEFOnline — Design System

**Versão:** v1.1
**Inspiração:** Stripe (payment-infrastructure aesthetic)
**Status:** especificação ativa

> Este documento é **neutro em relação a framework e biblioteca de UI**. Os tokens descritos abaixo (cores, tipografia, espaçamento, raios e sombras) devem ser implementados como variáveis CSS / equivalente em qualquer stack frontend escolhida.

---

## Filosofia

Estética de infraestrutura de pagamento: superfícies brancas, neutros claros como base da composição, tipografia leve em Inter, e um único acento interativo (indigo `#635BFF`) que dirige a atenção. **O sistema é flat** — sem gradientes, sem decoração extra. Espaço negativo é feature, não acidente.

Três regras de ouro:

1. **Tertiary (`#635BFF`) é o único condutor de interação.** Reserve para um CTA principal por tela. Não use em decoração, não use em mais de uma ação por tela.
2. **Negative space carrega a composição.** Neutral (`#F6F9FC`) é a base da página; surfaces brancas (`#FFFFFF`) flutuam sobre ele com sombra mínima.
3. **Flat por design.** Não introduzir gradientes. Não introduzir um segundo acento concorrente com Tertiary. Não usar sombras pesadas.

---

## Tokens

### Cores

| Token | Hex | Função |
|---|---|---|
| `primary` | `#0A2540` | Headlines e texto core |
| `secondary` | `#425466` | Bordas, captions, metadata |
| `tertiary` | `#635BFF` | **Único** acento interativo (botões primários, links ativos, focus rings) |
| `neutral` | `#F6F9FC` | Background da página |
| `surface` | `#FFFFFF` | Cards, popovers, inputs |
| `on-primary` | `#FFFFFF` | Texto sobre Tertiary (botão primário) |

Cores semânticas adicionais:

| Token | Uso | Valor |
|---|---|---|
| `destructive` | Erro, validação negativa, ação irreversível | `#E11D48` |
| `border` | **Separa** — divisória de seção, contorno de card. Não delimita nada com que se interaja | `#E3E8EE` |
| `input` | **Delimita componente interativo** — a borda que identifica um campo como campo. Piso de 3:1 (WCAG 2.1 §1.4.11) | `#7C8798` (v1.1 — DDR-007) |
| `ring` | Focus ring acessível | Tertiary com 40% alpha — `hsla(243, 100%, 67%, 0.4)` |

### Tipografia

Família única: **Inter** (300, 400, 500, 600). A forma de carregar a fonte fica a critério da stack escolhida (CDN, self-host, plugin de framework, etc.).

| Nível | Family | Size | Weight | Letter-spacing | Line-height |
|---|---|---:|---:|---:|---:|
| `display` | Inter | 5rem (80px) | 300 | -0.04em | 1.05 |
| `h1` | Inter | 2.5rem (40px) | 500 | -0.02em | 1.15 |
| `h2` | Inter | 1.75rem (28px) | 500 | -0.01em | 1.2 |
| `h3` | Inter | 1.25rem (20px) | 500 | 0 | 1.3 |
| `body` | Inter | 0.98rem (~15.7px) | 400 | 0 | 1.6 |
| `body-sm` | Inter | 0.875rem (14px) | 400 | 0 | 1.55 |
| `label` | Inter | 0.72rem (~11.5px) | 600 | 0.02em | 1.4 |
| `code` | JetBrains Mono ou ui-monospace | 0.875rem | 400 | 0 | 1.55 |

Regra: **nunca usar peso 700 ou maior.** O design depende da elegância do peso 300/500. Bold visual sai do contraste de tamanho e cor, não do peso.

### Espaçamento

Escala compacta:

| Token | Valor |
|---|---:|
| `xs` | 4px |
| `sm` | 8px |
| `md` | 16px |
| `lg` | 32px |
| `xl` | 64px |
| `2xl` | 96px |

### Border-radius

| Token | Valor | Uso |
|---|---:|---|
| `sm` | 6px | Inputs, badges, chips |
| `md` | 10px | Botões, dropdowns |
| `lg` | 16px | Cards, modals, popovers grandes |
| `full` | 9999px | Avatares, dots de status |

### Sombras

Sombras sutis. Nunca sombras pretas saturadas.

| Token | Valor |
|---|---|
| `shadow-sm` | `0 1px 2px rgba(10, 37, 64, 0.04)` |
| `shadow-md` | `0 4px 12px rgba(10, 37, 64, 0.06)` |
| `shadow-lg` | `0 12px 32px rgba(10, 37, 64, 0.08)` |

---

## Componentes-base

### button-primary

- `background: tertiary` (#635BFF)
- `color: on-primary` (#FFFFFF)
- `padding: 12px 20px`
- `border-radius: md` (10px)
- `font: body weight 500`
- `hover: tertiary darker 8%`
- `focus: ring 2px tertiary @ 40%`
- `disabled: opacity 0.5, cursor not-allowed`

### button-secondary

- `background: surface` (#FFFFFF)
- `color: primary` (#0A2540)
- `border: 1px solid border` (#E3E8EE)
- `padding: 12px 20px`
- `border-radius: md`
- `font: body weight 500`
- `hover: background neutral` (#F6F9FC)

### button-ghost

- `background: transparent`
- `color: primary`
- `padding: 12px 20px`
- `border-radius: md`
- `font: body weight 500`
- `hover: background neutral`

### card

- `background: surface`
- `color: primary`
- `border: 1px solid border`
- `border-radius: lg` (16px)
- `padding: 24px`
- `shadow: shadow-sm` (estado default)

### input

- `background: surface`
- `color: primary`
- `border: 1px solid input` (#E3E8EE)
- `border-radius: sm` (6px)
- `padding: 10px 12px`
- `font: body`
- `placeholder: secondary` (#425466)
- `focus: border tertiary, ring 2px tertiary @ 40%`
- `disabled: background neutral, color secondary`

### label

- `font: label` (0.72rem, weight 600, letter-spacing 0.02em)
- `color: secondary`
- `text-transform: uppercase`
- `margin-bottom: xs` (4px)

### link

- `color: tertiary`
- `text-decoration: none`
- `hover: underline + tertiary 10% darker`

---

### input-moeda e input-percentual

- Campos de `.input-affix`, com o símbolo dentro do campo e o texto alinhado à direita.
- Obrigatórios para todo dinheiro e percentual digitados (RNF-UI-01).
- Nunca escrever a máscara na tela nem repetir `(R$)` ou `(%)` no rótulo.

## Do's and Don'ts (consolidado)

**Do:**

- Usar Tertiary para **uma** ação por tela.
- Deixar Neutral conduzir a composição.
- Confiar em contraste de tamanho e peso 300 para hierarquia.
- Usar `border` (#E3E8EE) para separar áreas em vez de sombras pesadas.

**Don't:**

- Introduzir gradientes (mesmo que o nome do tema seja "purple gradients" — é referência ao design Stripe, não à execução).
- Misturar Tertiary com um segundo acento concorrente.
- Usar font-weight 700+.
- Usar cores ad-hoc fora dos tokens deste documento.
- Esconder o CTA principal em meio a outros botões — ele é único.

---

## Componentes da matriz de farol (caso especial)

O farol semáforo (`verde | amarelo | vermelho | cinza`) é uma exceção ao single-accent. Ele é informação semântica, não interação. Cores propostas (alinhadas com Anexo E):

**Dois tokens por farol (v1.1 — DDR-006).** A cor viva é **pigmento**: tinge fundo e borda. A tinta (`-texto`) é o que **escreve** — texto e ícone sobre esse fundo tingido. O par existe porque o pill pinta o fundo com 10–15% da própria cor, e nessa composição a cor viva reprova AA como texto (2,27–4,13:1 contra piso de 4,5:1).

| Farol | Pigmento | Tinta | Contraste da tinta sobre o pill |
|---|---|---|---|
| Verde | `#0E9F6E` — `farol-verde` | `#0B7A55` — `farol-verde-texto` | 4,76:1 |
| Amarelo | `#D97706` — `farol-amarelo` | `#9E5704` — `farol-amarelo-texto` | 4,84:1 |
| Vermelho | `#DC2626` — `farol-vermelho` | `#CA2121` — `farol-vermelho-texto` | 4,79:1 |
| Cinza (indisponível / NCG absoluto) | `#94A3B8` — `farol-cinza` | `#596B86` — `farol-cinza-texto` | 4,79:1 |

Use em badges, ícones ou pequenos blocos de cor — **nunca** como background de tela ou de card grande.

O fundo do pill é **opaco** (`color-mix` com `surface`), não alpha: como translúcido, ele somava com o tingimento do card do Resumo Executivo e o mesmo componente mudava de legibilidade conforme onde era colocado.

---

## Implementação

Implementação concreta (sistema de classes, variáveis CSS, biblioteca de componentes, framework de UI) fica para a spec técnica de construção. A única exigência arquitetural é que os tokens acima sejam expostos de forma centralizada — alterar uma cor ou um tamanho não pode exigir busca em arquivos espalhados.

A área logada (app pós-login) e o hotsite público compartilham os mesmos tokens; consistência visual entre as duas superfícies é critério de aceite.

---

## Evolução

Promovida de **alpha** para **v1** em 2026-05-25 após primeira aplicação real em código pela STORY-019 (EPIC-004 — app shell + materialização dos tokens). Mudanças no design system depois de v1 exigem bump de versão neste arquivo e avaliação de impacto em telas já implementadas.

---

## Histórico

- **2026-11-04 — token `highlight-pulse` removido (PDR-015).** Só atendia os destaques clicáveis do Resumo Executivo, que saiu do relatório.
- **2026-08-06 — v1.1: tinta do farol e borda de campo (STORY-059, DDR-006 e DDR-007).** Primeiro bump desde a promoção a v1, e o motivo não foi tela nova — foi contraste. (a) **Quatro tokens novos** `--color-farol-<cor>-texto`: a cor viva do farol reprovava AA como texto sobre o próprio fundo tingido (2,27–4,13:1), e o ícone reprovava até o piso de 3:1 nos casos amarelo e cinza. A separação pigmento/tinta preserva a identidade cromática do pill — a alternativa, escurecer o token único, levaria junto o fundo e o amarelo viraria marrom, além de escurecer sem necessidade a borda do card do Resumo Executivo e a faixa de alerta do Quiz, que consomem os mesmos tokens sem ter problema de contraste. O fundo do pill passou de alpha para `color-mix` opaco: como translúcido, somava com o tingimento do card do Resumo Executivo e derrubava o rótulo de 4,76 para 4,2:1. (b) **`--color-input` mudou de valor** (`#E3E8EE` → `#7C8798`): media 1,23:1 contra os 3:1 da WCAG 2.1 §1.4.11 em **todas** as telas de formulário do produto. `border` e `input` eram idênticos desde o v1, o que fazia a distinção parecer decorativa — passam a significar coisas diferentes: `border` separa, `input` delimita componente interativo. Impacto assumido: cadastro, login, nova empresa e o Quiz inteiro mudam de aparência, e o produto se afasta um passo da leveza Stripe em favor de legibilidade. Ratificado pelo PO em 2026-08-06. Gate de acessibilidade da página do relatório: **85 → 100** (Lighthouse 12.8.2).
- **2026-06-02 — token `highlight-pulse` (STORY-040).** Adicionado token semântico `--color-highlight-pulse` (`rgba(99, 91, 255, 0.16)` — tonalidade suave do Tertiary) para o realce temporário (~1s) da linha/card-alvo quando o usuário clica num destaque do Resumo Executivo e a página rola até o indicador na tabela detalhada. Mantém a filosofia single-accent (deriva do Tertiary, não introduz nova matiz). Materializado em `app/resources/css/tokens.css`; consumido apenas via `var(--color-highlight-pulse)` na animação `indicador-highlight` de `app.css`. Não altera tokens existentes — mudança aditiva, sem bump de versão.
- **2026-05-25 — promoção para v1.** Primeira aplicação real do design-system em código pela STORY-019. Tokens materializados em `app/resources/css/tokens.css` via Tailwind v4 `@theme` (IDR-008). Componentes-base Blade implementados (`<x-button>` 3 variants, `<x-card>`, `<x-input>`, `<x-label>`, `<x-link>`, `<x-logo>`, `<x-breadcrumb>`, `<x-page-header>`, `<x-footer-version>`) consumindo só CSS vars. Telas existentes do EPIC-001 (`/cadastro`, `/login`, `/home`, `/empresas/nova`, `/empresas/{id}/show`) refatoradas para usar o novo layout — drift de paleta da STORY-016 (`#1f2937`, `#2563eb`, `#f9fafb`) eliminado. Nenhuma mudança de token foi necessária: paleta, tipografia, espaçamento, raios e sombras passaram intactos no teste real. Único ajuste implícito foi a definição de variantes hover/active derivadas do Tertiary (`--color-tertiary-hover: #4F46E5`, `--color-tertiary-active: #4338CA`) — antes ficavam descritas como "8% darker" sem hex concreto. Próxima revisão de tokens só com sinal concreto de outra aplicação (EPIC-002 / EPIC-008).

---

## Referências

- `especificacao/V2/especificacao-funcional.md` §3.3, §4.x — telas que consomem este design.
- `especificacao/V2/anexos/anexo-E-faixas-semaforo.md` — definição dos faróis usados no relatório.
