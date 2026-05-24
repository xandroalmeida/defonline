# Design System — DEFOnline (esqueleto inicial)

> Este é o template para inicializar o Design System **vivo** em `defonline-docs/project-state/design/system/`. Ele **herda** do DS canônico em `defonline-docs/especificacao/V2/design-system.md` — copie os tokens reais de lá (paleta Stripe-like com `tertiary #635BFF`, Inter, sombras sutis, raios curtos). Toda inclusão/alteração relevante de componente, token ou padrão passa por um DDR (Design Decision Record); mudança de fundação visual (paleta, tipografia, regra de "único acento") exige DDR **e** atualização coordenada da especificação ou supersede formal.

A estrutura recomendada do DS são 4 arquivos sob `project-state/design/system/`:

```
project-state/design/system/
├── README.md              ← entrada (visão + como navegar)
├── tokens.md              ← fundações: cor, tipografia, espaçamento, raio, sombra, motion
├── components.md          ← biblioteca de componentes com variantes e estados
├── patterns.md            ← padrões compostos (form, listagem, wizard, vazio, erro)
└── voice-and-tone.md      ← tom, microcopy, vocabulário
```

A seguir, esqueleto sugerido para cada arquivo.

---

## `README.md`

```markdown
# Design System DEFOnline

Vocabulário visual e de interação compartilhado pelas telas do DEFOnline. Stack-agnóstico — descreve **comportamento e visual em termos de tokens e estados**, não em código de framework.

## Como usar

- Antes de desenhar uma tela nova, leia este DS — provavelmente o que você precisa já existe.
- Componente novo entra por **DDR** primeiro (ver `defonline-docs/skills/designer/templates/ddr.md`), não direto.
- Spec de tela referencia componentes pelo id (`ds_components_used` no frontmatter do spec).

## Navegação

- `tokens.md` — fundações (cor, tipografia, espaçamento, raio, sombra, motion).
- `components.md` — biblioteca de componentes.
- `patterns.md` — padrões compostos recorrentes.
- `voice-and-tone.md` — tom de voz e vocabulário.

## Status

Versão: 0.1 — esqueleto inicial.
Última atualização: YYYY-MM-DD.
```

---

## `tokens.md`

```markdown
# Tokens

> Tokens são as fundações. Toda decisão visual sai daqui. **Não use valor cru** em spec — use token.

## Cor

Valores canônicos vêm de `especificacao/V2/design-system.md`. Tabela espelhada aqui para conveniência:

| Token | Valor | Uso |
|---|---|---|
| `primary` | `#0A2540` | headlines e texto core |
| `secondary` | `#425466` | bordas, captions, metadata |
| `tertiary` | `#635BFF` | **único** acento interativo — CTA primário, link ativo, focus ring |
| `neutral` | `#F6F9FC` | background da página |
| `surface` | `#FFFFFF` | cards, popovers, inputs |
| `on-primary` | `#FFFFFF` | texto sobre `tertiary` |
| `destructive` | `#E11D48` | erro, validação negativa, ação irreversível |
| `border` / `input` | `#E3E8EE` | bordas neutras / borda de input default |
| `ring` | `hsla(243,100%,67%,0.4)` | focus ring acessível (Tertiary 40% alpha) |

**Restrições de uso (regras de ouro do DS):**

- `tertiary` é o **único** condutor de interação. Reserve para **um** CTA principal por tela. Não use em decoração nem em mais de uma ação por tela.
- `neutral` (`#F6F9FC`) é base da página; `surface` (`#FFFFFF`) flutua sobre ela com sombra mínima.
- **Flat por design.** Sem gradientes. Sem segundo acento concorrente. Sem sombras pesadas.
- Cor de feedback nunca é o único canal de informação (sempre acompanha ícone ou texto — acessibilidade).

> Qualquer adição/alteração a esta paleta exige DDR **e** atualização coordenada de `especificacao/V2/design-system.md` (ou supersede formal).

## Tipografia

- **Família única:** Inter (300, 400, 500, 600). Code: JetBrains Mono ou `ui-monospace`.
- **Regra de peso:** nunca usar 700+. Contraste sai de tamanho e cor, não de peso bold.
- Escala canônica (de `especificacao/V2/design-system.md` — espelhada aqui):

| Nível | Size | Weight | Letter-spacing | Line-height |
|---|---:|---:|---:|---:|
| `display` | 5rem (80px) | 300 | -0.04em | 1.05 |
| `h1` | 2.5rem (40px) | 500 | -0.02em | 1.15 |
| `h2` | 1.75rem (28px) | 500 | -0.01em | 1.2 |
| `h3` | 1.25rem (20px) | 500 | 0 | 1.3 |
| `body` | 0.98rem (~15.7px) | 400 | 0 | 1.6 |
| `body-sm` | 0.875rem (14px) | 400 | 0 | 1.55 |
| `label` | 0.72rem (~11.5px) | 600 | 0.02em | 1.4 |
| `code` | 0.875rem | 400 | 0 | 1.55 |

## Espaçamento

Escala compacta canônica (de `especificacao/V2/design-system.md`):

| Token | Valor |
|---|---:|
| `xs` | 4px |
| `sm` | 8px |
| `md` | 16px |
| `lg` | 32px |
| `xl` | 64px |
| `2xl` | 96px |

## Raio (border-radius)

| Token | Valor | Uso |
|---|---:|---|
| `radius.sm` | 6px | inputs, badges, chips |
| `radius.md` | 10px | botões, dropdowns |
| `radius.lg` | 16px | cards, modals, popovers grandes |
| `radius.full` | 9999px | avatares, dots de status |

## Sombra

Sutis. Nunca pretas saturadas. Sombra com parcimônia — tom sério não combina com profundidade exagerada.

| Token | Valor |
|---|---|
| `shadow-sm` | `0 1px 2px rgba(10, 37, 64, 0.04)` |
| `shadow-md` | `0 4px 12px rgba(10, 37, 64, 0.06)` |
| `shadow-lg` | `0 12px 32px rgba(10, 37, 64, 0.08)` |

## Motion (transições)

| Token | Duração | Easing | Uso |
|---|---|---|---|
| `motion.fast` | 100ms | ease-out | feedback imediato (hover, press) |
| `motion.base` | 200ms | ease-in-out | mudanças de estado, abrir/fechar |
| `motion.slow` | 300ms | ease-in-out | transição entre telas, drawers |

> Transições têm propósito (orientar atenção), nunca decoração. Acima de 300ms → erro de design.

## Breakpoints

| Token | Min-width | Apelido |
|---|---|---|
| `bp.mobile` | 360px | mobile (base — mobile-first) |
| `bp.tablet` | 768px | tablet |
| `bp.desktop` | 1024px | desktop |
| `bp.wide` | 1440px | desktop largo |
```

---

## `components.md`

```markdown
# Componentes

> Cada componente: id, descrição, anatomia, variantes, estados, regras de uso, exemplo visual.

## Como ler

- **id** é o que o spec de tela referencia em `ds_components_used`.
- **Estados** cobrem `default`, `hover`, `focus`, `active`, `disabled`, `loading`, `error` quando aplicáveis.
- **Não usar quando** é tão importante quanto **usar quando** — restringe.

---

### `button.primary`

**Descrição:** ação principal de uma tela ou bloco. Existe **no máximo uma por contexto**.

**Anatomia:** label (obrigatório), ícone opcional à esquerda, padding `space.3 space.5`, raio `radius.md`, cor `brand.primary`, texto `text.body.strong` em branco.

**Variantes:** tamanho `md` (padrão), `lg` (CTA principal de tela em mobile — toque ≥ 48px).

**Estados:**

| Estado | Descrição |
|---|---|
| default | `brand.primary` |
| hover | `brand.primary.hover` |
| focus | anel `border.focus` 2px |
| active | escurece 4% adicional |
| disabled | opacidade 50%, `cursor: not-allowed` |
| loading | spinner inline à esquerda do label; clique bloqueado |

**Usar quando:** ação principal e única da tela ou seção.

**Não usar quando:** ação destrutiva (usar `button.danger`); ação secundária (usar `button.secondary`).

```
+-------------------------+
|   Salvar diagnóstico    |
+-------------------------+
```

---

### `button.secondary`

(mesmo formato — descrever brevemente)

---

### `input.text`

**Anatomia:** label acima (obrigatório, exceto em busca), input, hint opcional abaixo, mensagem de erro abaixo quando aplicável.

**Estados:** default / focus / disabled / error / readonly.

**Acessibilidade:** label sempre associado (`for`/`id`), erro associado via `aria-describedby`, foco visível obrigatório.

(mesmo formato)

---

### `card`

(mesmo formato)

---

### `empty-state`

**Anatomia:** ilustração opcional (apenas se comunica algo — não decorativa), título curto, instrução, CTA primário.

**Regra:** estado vazio **sempre** instrui o próximo passo. "Nenhum dado" sozinho é proibido.

(mesmo formato)

---

### `toast` / `alert`

(mesmo formato — incluir variantes success/warning/danger/info)

---

> **Lista inicial mínima a cobrir até EPIC-001:** `button.primary`, `button.secondary`, `button.danger`, `input.text`, `input.select`, `input.checkbox`, `input.radio`, `card`, `empty-state`, `toast`, `modal`, `table`, `nav.sidebar` (desktop) + `nav.bottom` (mobile), `breadcrumb`, `pagination`, `skeleton`, `badge`, `tag`.
```

---

## `patterns.md`

```markdown
# Padrões compostos

> Combinações recorrentes de componentes para resolver problemas frequentes. Cada padrão evita reinventar a roda.

## `pattern.form`

Composição: campos verticalmente empilhados, label acima, mensagem de erro associada, CTA primário ao final.

**Regras:**

- Form longo (>5 campos) é candidato a `pattern.wizard`.
- Validação inline preferida (no blur do campo).
- Mensagem de erro nunca é só cor — sempre texto associado.

(sketch inline)

## `pattern.wizard`

Composição: passos numerados visíveis, navegação anterior/próximo, possibilidade de salvar rascunho.

**Regras:**

- Use para fluxos com >5 campos ou decisão em estágios.
- Sempre mostre progresso ("Passo 2 de 4").
- Permita voltar sem perder dado preenchido.

(sketch inline)

## `pattern.listing`

Composição: filtros (drawer/colapsado em mobile, lateral em desktop), lista/tabela, paginação, estado vazio, ordenação.

**Regras:**

- Tabela com >7 colunas vira lista de cards no mobile (não scroll horizontal infinito).
- Sempre tem estado vazio próprio.
- Filtros mantêm-se entre navegações (URL ou sessão).

(sketch inline)

## `pattern.empty`

Composição: empty-state padronizado (`empty-state` componente) com CTA contextual.

## `pattern.error`

Composição: erro recuperável (toast + retry) vs erro de tela (página dedicada com instrução e caminho).
```

---

## `voice-and-tone.md`

```markdown
# Voice & Tone

> Como o DEFOnline fala com o usuário. Detalhe pleno em `defonline-docs/skills/designer/references/tone-and-voice.md` — este arquivo é o **resumo aplicável** no dia-a-dia.

## Tom

- **Profissional, direto, respeitoso.** O usuário é profissional ocupado — você não fala como amigo.
- **Sem entusiasmo performático.** "Tudo certo!" > "Uhuu! Foi! 🎉"
- **Sem culpar o usuário.** "Não encontramos esse CNPJ" > "CNPJ inválido — verifique."
- **Sem jargão técnico em microcopy.** "Não conseguimos salvar agora" > "Erro 500 — falha no servidor."

## Vocabulário

Use o `glossary.md` do PO. Termos do negócio: `Diagnóstico`, `Empresa Analisada`, `Indicador`, `MPE`. **Não rebatize.**

## Padrões de microcopy

| Situação | Padrão | Exemplo |
|---|---|---|
| CTA primário | verbo no infinitivo | "Salvar diagnóstico" |
| CTA secundário | verbo no infinitivo, neutro | "Cancelar" |
| Confirmação destrutiva | nomeia o objeto | "Excluir Empresa Analisada?" |
| Sucesso | curto, sem emoji | "Diagnóstico salvo." |
| Erro recuperável | o que aconteceu + o que fazer | "Não foi possível salvar. Tentar novamente." |
| Vazio | o que falta + como conseguir | "Você ainda não cadastrou Empresas. Cadastrar a primeira." |
| Loading | (preferir skeleton — sem texto) | — |
| Placeholder | exemplo, não instrução | `Ex.: 00.000.000/0000-00` (CNPJ) |
```
